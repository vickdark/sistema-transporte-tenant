<?php

namespace App\Models;

use Database\Factories\PartFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Parte del catálogo de mantenimiento (motor, frenos, aceite…). Cada vehículo que la tiene indica
 * cada cuántos kilómetros se le hace mantenimiento (VehiclePart). En el legacy el catálogo era un
 * ENUM de la base que se alteraba al crear o eliminar partes.
 *
 * @property int $id
 * @property string $name
 */
class Part extends Model
{
    /** @use HasFactory<PartFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['name'];

    /**
     * @return HasMany<VehiclePart, $this>
     */
    public function vehicleParts(): HasMany
    {
        return $this->hasMany(VehiclePart::class);
    }

    /**
     * Parte con el mismo nombre sin distinguir mayúsculas ni espacios.
     */
    public static function findByName(string $name, ?int $ignoreId = null): ?self
    {
        return static::query()
            ->whereRaw("lower(regexp_replace(trim(name), '\\s+', ' ', 'g')) = ?", [mb_strtolower(self::clean($name))])
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->first();
    }

    public static function clean(string $name): string
    {
        return (string) preg_replace('/\s+/', ' ', trim($name));
    }
}
