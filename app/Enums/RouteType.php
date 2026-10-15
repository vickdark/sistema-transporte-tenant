<?php

namespace App\Enums;

/**
 * Tipo de ruta (mismas opciones que el legacy).
 */
enum RouteType: string
{
    case Urban = 'urban';
    case Rural = 'rural';
    case Mountain = 'mountain';
    case Coastal = 'coastal';
    case Desert = 'desert';

    public function label(): string
    {
        return match ($this) {
            self::Urban => 'Urbana',
            self::Rural => 'Rural',
            self::Mountain => 'Montañosa',
            self::Coastal => 'Costera',
            self::Desert => 'Desértica',
        };
    }
}
