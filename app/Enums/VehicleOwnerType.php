<?php

namespace App\Enums;

/**
 * Dueño de un vehículo. Mixto es un tercero registrado como aliado y como proveedor (el vehículo
 * queda a nombre de los dos); al registrarlo se elige proveedor o aliado y el otro se completa solo.
 */
enum VehicleOwnerType: string
{
    case Company = 'company';
    case Supplier = 'supplier';
    case Ally = 'ally';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Company => 'Empresa',
            self::Supplier => 'Proveedor',
            self::Ally => 'Aliado',
            self::Mixed => 'Mixto',
        };
    }
}
