<?php

namespace App\Models;

use App\Enums\ClientType;
use App\Enums\IdentificationType;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cliente de la empresa o de uno de sus aliados (`ally_id`). Como en el legacy, un usuario que
 * pertenece a un aliado ve solo los clientes de ese aliado, y los demás usuarios solo los de la
 * empresa (`visibleTo`).
 *
 * @property int $id
 * @property ClientType $type
 * @property IdentificationType $identification_type
 * @property string $identification
 * @property string|null $check_digit
 * @property string|null $branch_code
 * @property string|null $first_names
 * @property string|null $last_names
 * @property string|null $business_name
 * @property string|null $trade_name
 * @property string $city
 * @property string $address
 * @property string $phone
 * @property string|null $extension
 * @property string|null $email obligatorio en los clientes creados en el sistema; los migrados pueden no tenerlo
 * @property bool $is_active
 * @property int|null $ally_id dueño del cliente; null = la empresa
 * @property string|null $legacy_source tabla legacy de la que se migró (ver App\Actions\Migrations)
 * @property string|null $legacy_id
 * @property-read Ally|null $ally
 */
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'identification_type',
        'identification',
        'check_digit',
        'branch_code',
        'first_names',
        'last_names',
        'business_name',
        'trade_name',
        'city',
        'address',
        'phone',
        'extension',
        'email',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ClientType::class,
            'identification_type' => IdentificationType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Ally, $this>
     */
    public function ally(): BelongsTo
    {
        return $this->belongsTo(Ally::class);
    }

    /**
     * @return HasMany<Contract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Clientes del mismo dueño que el usuario: los de su aliado, o los de la empresa si no
     * pertenece a ninguno.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $user->ally_id !== null
            ? $query->where('ally_id', $user->ally_id)
            : $query->whereNull('ally_id');
    }

    /**
     * El correo es obligatorio salvo para los clientes migrados del legacy, que no lo tenían.
     */
    public function requiresEmail(): bool
    {
        return $this->legacy_id === null;
    }

    public function isVisibleTo(User $user): bool
    {
        return $this->ally_id === $user->ally_id;
    }

    /**
     * Nombre para mostrar: razón social para empresas, nombres y apellidos para personas (con
     * respaldo en el otro, porque el legacy tiene registros incompletos).
     */
    public function displayName(): string
    {
        $personName = trim("{$this->first_names} {$this->last_names}");
        $companyName = $this->business_name ?: $this->trade_name;

        return $this->type === ClientType::Company
            ? ($companyName ?: $personName)
            : ($personName ?: (string) $companyName);
    }
}
