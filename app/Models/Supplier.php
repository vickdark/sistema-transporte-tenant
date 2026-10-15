<?php

namespace App\Models;

use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Proveedor de la empresa: un tercero (persona o empresa) que aporta vehículos y conductores.
 * Si también está registrado como aliado y se vinculan (`ally_id`), es "mixto".
 *
 * @property int $id
 * @property string $name
 * @property string|null $nit
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $email
 * @property int|null $ally_id
 * @property string|null $legacy_source tabla legacy de la que se migró (ver App\Actions\Migrations)
 * @property string|null $legacy_id
 * @property-read Ally|null $ally
 */
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'nit', 'phone', 'address', 'email'];

    /**
     * Su registro como aliado cuando es mixto.
     *
     * @return BelongsTo<Ally, $this>
     */
    public function ally(): BelongsTo
    {
        return $this->belongsTo(Ally::class);
    }

    /**
     * Vehículos a su nombre (si es mixto, los mismos que su gemelo).
     *
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isMixed(): bool
    {
        return $this->ally_id !== null;
    }
}
