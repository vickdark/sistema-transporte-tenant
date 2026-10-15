<?php

namespace App\Models;

use Database\Factories\AgreementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Convenio con otra empresa de transporte: sus vehículos operan bajo el convenio y el FUEC lo
 * muestra. Un vehículo está como mucho en un convenio.
 *
 * @property int $id
 * @property string $nit
 * @property string $business_name
 * @property string|null $legacy_source tabla legacy de la que se migró (ver App\Actions\Migrations)
 * @property string|null $legacy_id
 */
class Agreement extends Model
{
    /** @use HasFactory<AgreementFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['nit', 'business_name'];

    /**
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }
}
