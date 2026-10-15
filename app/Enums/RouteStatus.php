<?php

namespace App\Enums;

/**
 * Estado de una ruta. Solo las activas se ofrecen al crear una orden; las inactivas se listan
 * aparte (equivalen a "eliminadas" sin perder el registro).
 */
enum RouteStatus: string
{
    case Active = 'active';
    case Maintenance = 'maintenance';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activa',
            self::Maintenance => 'En mantenimiento',
            self::Inactive => 'Inactiva',
        };
    }
}
