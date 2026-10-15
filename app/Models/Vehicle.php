<?php

namespace App\Models;

use App\Enums\FuelType;
use App\Enums\VehicleClass;
use App\Enums\VehicleOwnerType;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Vehículo de la empresa, de un proveedor, de un aliado o de un tercero mixto (los dos ids). Un
 * usuario que pertenece a un aliado o a un proveedor solo ve los vehículos de ese tercero
 * (`visibleTo`); los demás usuarios ven todos, como en el legacy.
 *
 * @property int $id
 * @property string $plate
 * @property string|null $internal_number
 * @property string $current_mileage
 * @property Carbon $registration_date
 * @property string $registration_place
 * @property int $model_year
 * @property string $brand
 * @property string $line línea (legacy `ClaseVehiculo`)
 * @property VehicleClass $vehicle_class clase (legacy `TipoVehiculo`)
 * @property string $engine_number
 * @property string $chassis_number
 * @property string $color
 * @property int|null $displacement cilindraje en cc
 * @property FuelType|null $fuel_type
 * @property int|null $supplier_id
 * @property int|null $ally_id
 * @property int|null $agreement_id
 * @property bool $is_active
 * @property string|null $legacy_source tabla legacy de la que se migró (ver App\Actions\Migrations)
 * @property string|null $legacy_id
 * @property-read Supplier|null $supplier
 * @property-read Ally|null $ally
 * @property-read Agreement|null $agreement
 */
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    /**
     * Documentos y fotos en el disco local, que la tenancy aísla por empresa.
     */
    public const FILES_DISK = 'local';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plate',
        'internal_number',
        'current_mileage',
        'registration_date',
        'registration_place',
        'model_year',
        'brand',
        'line',
        'vehicle_class',
        'engine_number',
        'chassis_number',
        'color',
        'displacement',
        'fuel_type',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_mileage' => 'decimal:2',
            'registration_date' => 'date',
            'model_year' => 'integer',
            'vehicle_class' => VehicleClass::class,
            'displacement' => 'integer',
            'fuel_type' => FuelType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<Ally, $this>
     */
    public function ally(): BelongsTo
    {
        return $this->belongsTo(Ally::class);
    }

    /**
     * @return BelongsTo<Agreement, $this>
     */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    /**
     * Todos los documentos, vigentes y reemplazados (historial).
     *
     * @return HasMany<VehicleDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(VehicleDocument::class);
    }

    /**
     * El documento vigente de cada tipo.
     *
     * @return HasMany<VehicleDocument, $this>
     */
    public function currentDocuments(): HasMany
    {
        return $this->documents()->whereNull('replaced_at');
    }

    /**
     * @return HasMany<VehiclePhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(VehiclePhoto::class);
    }

    /**
     * Partes del vehículo con su kilometraje entre mantenimientos.
     *
     * @return HasMany<VehiclePart, $this>
     */
    public function vehicleParts(): HasMany
    {
        return $this->hasMany(VehiclePart::class);
    }

    /**
     * Pone el vehículo a nombre de la empresa (sin tercero), de un proveedor o de un aliado. Si el
     * tercero es mixto queda a nombre de los dos, como hacía el trigger del legacy.
     */
    public function assignOwner(Supplier|Ally|null $owner): void
    {
        $this->supplier_id = match (true) {
            $owner instanceof Supplier => $owner->id,
            $owner instanceof Ally => $owner->supplier?->id,
            default => null,
        };
        $this->ally_id = match (true) {
            $owner instanceof Ally => $owner->id,
            $owner instanceof Supplier => $owner->ally_id,
            default => null,
        };
    }

    public function ownerType(): VehicleOwnerType
    {
        return match (true) {
            $this->supplier_id !== null && $this->ally_id !== null => VehicleOwnerType::Mixed,
            $this->supplier_id !== null => VehicleOwnerType::Supplier,
            $this->ally_id !== null => VehicleOwnerType::Ally,
            default => VehicleOwnerType::Company,
        };
    }

    /**
     * Nombre del dueño: el del proveedor o el del aliado, o null si es de la empresa.
     */
    public function ownerName(): ?string
    {
        return $this->supplier?->name ?? $this->ally?->name;
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOwnedBy(Builder $query, VehicleOwnerType $type): void
    {
        match ($type) {
            VehicleOwnerType::Company => $query->whereNull('supplier_id')->whereNull('ally_id'),
            VehicleOwnerType::Supplier => $query->whereNotNull('supplier_id')->whereNull('ally_id'),
            VehicleOwnerType::Ally => $query->whereNull('supplier_id')->whereNotNull('ally_id'),
            VehicleOwnerType::Mixed => $query->whereNotNull('supplier_id')->whereNotNull('ally_id'),
        };
    }

    /**
     * Vehículos que el usuario puede ver: los de su aliado o su proveedor si pertenece a uno; si
     * no, todos.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query
            ->when($user->ally_id !== null, fn (Builder $query) => $query->where('ally_id', $user->ally_id))
            ->when($user->supplier_id !== null, fn (Builder $query) => $query->where('supplier_id', $user->supplier_id));
    }

    public function isVisibleTo(User $user): bool
    {
        return ($user->ally_id === null || $user->ally_id === $this->ally_id)
            && ($user->supplier_id === null || $user->supplier_id === $this->supplier_id);
    }
}
