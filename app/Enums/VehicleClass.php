<?php

namespace App\Enums;

/**
 * Clase de vehículo (legacy `TipoVehiculo`, mismas opciones que su formulario). `legacyText()` es
 * el valor que guarda el legacy.
 */
enum VehicleClass: string
{
    case Pickup4x4 = 'pickup_4x4';
    case Bus = 'bus';
    case ClosedVan = 'closed_van';
    case Minibus = 'minibus';
    case Car = 'car';
    case FlatbedPickup = 'flatbed_pickup';
    case Jeep = 'jeep';
    case Microbus = 'microbus';

    public function label(): string
    {
        return match ($this) {
            self::Pickup4x4 => 'Camioneta 4x4',
            self::Bus => 'Bus',
            self::ClosedVan => 'Camioneta cerrada',
            self::Minibus => 'Buseta',
            self::Car => 'Automóvil',
            self::FlatbedPickup => 'Camioneta platón',
            self::Jeep => 'Campero',
            self::Microbus => 'Microbús',
        };
    }

    public function legacyText(): string
    {
        return match ($this) {
            self::Pickup4x4 => 'Camioneta 4x4',
            self::Bus => 'Bus',
            self::ClosedVan => 'Camioneta Cerrada',
            self::Minibus => 'Buseta',
            self::Car => 'Automovil',
            self::FlatbedPickup => 'Camioneta platon',
            self::Jeep => 'Campero',
            self::Microbus => 'MicroBus',
        };
    }

    public static function fromLegacyText(?string $text): ?self
    {
        $normalized = mb_strtolower(trim((string) $text));

        foreach (self::cases() as $case) {
            if (mb_strtolower($case->legacyText()) === $normalized) {
                return $case;
            }
        }

        return null;
    }
}
