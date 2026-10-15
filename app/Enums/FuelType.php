<?php

namespace App\Enums;

/**
 * Combustible del vehículo (mismas opciones que el legacy).
 */
enum FuelType: string
{
    case Gasoline = 'gasoline';
    case Diesel = 'diesel';
    case NaturalGas = 'natural_gas';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Gasoline => 'Gasolina',
            self::Diesel => 'Diésel',
            self::NaturalGas => 'Gas natural',
            self::Other => 'Otros',
        };
    }

    public static function fromLegacyText(string $text): ?self
    {
        return match (mb_strtolower(trim($text))) {
            'gasolina' => self::Gasoline,
            'diesel', 'diésel' => self::Diesel,
            'gas natural' => self::NaturalGas,
            'otros' => self::Other,
            default => null,
        };
    }
}
