<?php

namespace App\Models;

use Database\Factories\VehiclePartFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Parte que tiene un vehículo, con cada cuántos kilómetros se le hace mantenimiento (legacy
 * `partes_vehiculo.KilometrajeRequerido`).
 *
 * @property int $id
 * @property int $vehicle_id
 * @property int $part_id
 * @property int $maintenance_interval_km
 * @property string|null $legacy_source tabla legacy de la que se migró (ver App\Actions\Migrations)
 * @property string|null $legacy_id
 * @property-read Vehicle $vehicle
 * @property-read Part $part
 */
class VehiclePart extends Model
{
    /** @use HasFactory<VehiclePartFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['vehicle_id', 'part_id', 'maintenance_interval_km'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'maintenance_interval_km' => 'integer',
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
     * @return BelongsTo<Part, $this>
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }
}
