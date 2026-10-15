<?php

namespace App\Models;

use App\Enums\VehicleDocumentType;
use Database\Factories\VehicleDocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Documento de un vehículo (SOAT, revisión técnico-mecánica, pólizas…). De cada tipo hay uno
 * vigente (`replaced_at` null); al renovarlo, el anterior queda en el historial con su archivo.
 *
 * @property int $id
 * @property int $vehicle_id
 * @property VehicleDocumentType $type
 * @property string|null $number
 * @property Carbon|null $issued_on
 * @property Carbon|null $expires_on
 * @property string|null $file_path en el disco Vehicle::FILES_DISK
 * @property Carbon|null $replaced_at
 * @property string|null $legacy_source tabla legacy de la que se migró (ver App\Actions\Migrations)
 * @property string|null $legacy_id
 * @property-read Vehicle $vehicle
 */
class VehicleDocument extends Model
{
    /** @use HasFactory<VehicleDocumentFactory> */
    use HasFactory;

    /**
     * Días antes del vencimiento en que el documento se muestra "por vencer" (como el legacy).
     */
    public const EXPIRING_DAYS = 30;

    /**
     * @var list<string>
     */
    protected $fillable = ['type', 'number', 'issued_on', 'expires_on'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => VehicleDocumentType::class,
            'issued_on' => 'date',
            'expires_on' => 'date',
            'replaced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereNull('replaced_at');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->whereDate('expires_on', '<', today());
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeExpiring(Builder $query): void
    {
        $query->whereDate('expires_on', '>=', today())->whereDate('expires_on', '<=', today()->addDays(self::EXPIRING_DAYS));
    }

    /**
     * Estado según el vencimiento: vigente, por vencer (30 días o menos), vencido o sin vencimiento.
     *
     * @return 'valid'|'expiring'|'expired'|'no_expiry'
     */
    public function status(): string
    {
        return match (true) {
            $this->expires_on === null => 'no_expiry',
            $this->expires_on->lt(today()) => 'expired',
            $this->expires_on->lte(today()->addDays(self::EXPIRING_DAYS)) => 'expiring',
            default => 'valid',
        };
    }
}
