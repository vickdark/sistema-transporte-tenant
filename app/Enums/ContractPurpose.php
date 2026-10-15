<?php

namespace App\Enums;

/**
 * Objeto del contrato (lista fija del legacy). `legacyText()` es el texto que guarda el legacy y
 * que se imprime en el FUEC.
 */
enum ContractPurpose: string
{
    case Students = 'students';
    case Business = 'business';
    case Tourists = 'tourists';
    case SpecificGroup = 'specific_group';
    case Health = 'health';

    public function label(): string
    {
        return match ($this) {
            self::Students => 'Transporte de estudiantes',
            self::Business => 'Transporte empresarial',
            self::Tourists => 'Transporte de turistas',
            self::SpecificGroup => 'Grupo específico de usuarios',
            self::Health => 'Usuarios del servicio de salud',
        };
    }

    public function legacyText(): string
    {
        return match ($this) {
            self::Students => 'CONTRATO PARA TRANSPORTE DE ESTUDIANTES',
            self::Business => 'CONTRATO PARA TRANSPORTE EMPRESARIAL',
            self::Tourists => 'CONTRATO PARA TRANSPORTE DE TURISTAS',
            self::SpecificGroup => 'CONTRATO PARA GRUPO ESPECIFICO DE USUARIOS',
            self::Health => 'CONTRATO PARA TRANSPORTE DE USUARIOS DEL SERVICIO DE SALUD',
        };
    }

    public static function fromLegacyText(string $text): ?self
    {
        $normalized = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $text)));

        foreach (self::cases() as $case) {
            if ($case->legacyText() === $normalized) {
                return $case;
            }
        }

        return null;
    }
}
