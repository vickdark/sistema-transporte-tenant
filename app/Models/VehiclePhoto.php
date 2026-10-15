<?php

namespace App\Models;

use Database\Factories\VehiclePhotoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Foto de un vehículo, en el disco Vehicle::FILES_DISK. Como en el legacy, hasta cinco por vehículo.
 *
 * @property int $id
 * @property int $vehicle_id
 * @property string $path
 * @property string|null $legacy_source tabla legacy de la que se migró (ver App\Actions\Migrations)
 * @property string|null $legacy_id
 * @property-read Vehicle $vehicle
 */
class VehiclePhoto extends Model
{
    /** @use HasFactory<VehiclePhotoFactory> */
    use HasFactory;

    public const MAX_PER_VEHICLE = 5;

    /**
     * @var list<string>
     */
    protected $fillable = ['path'];

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
