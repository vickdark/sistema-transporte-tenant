<?php

namespace App\Models;

use Database\Factories\AllyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Aliado de la empresa: un tercero (persona o empresa) que presta servicios con sus propios
 * vehículos y conductores. Si también está registrado como proveedor y se vinculan, es "mixto".
 *
 * @property int $id
 * @property string $name
 * @property string|null $nit
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $email
 * @property string|null $legacy_source tabla legacy de la que se migró (ver App\Actions\Migrations)
 * @property string|null $legacy_id
 * @property-read Supplier|null $supplier
 */
class Ally extends Model
{
    /** @use HasFactory<AllyFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'nit', 'phone', 'address', 'email'];

    /**
     * Su registro como proveedor cuando es mixto.
     *
     * @return HasOne<Supplier, $this>
     */
    public function supplier(): HasOne
    {
        return $this->hasOne(Supplier::class);
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
        return $this->supplier !== null;
    }
}
