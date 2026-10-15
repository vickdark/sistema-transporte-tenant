<?php

namespace App\Enums;

/**
 * Tipo de documento de un cliente. El NIT exige dígito de verificación.
 */
enum IdentificationType: string
{
    case CitizenId = 'cc';
    case Nit = 'nit';

    public function label(): string
    {
        return match ($this) {
            self::CitizenId => 'Cédula de Ciudadanía',
            self::Nit => 'NIT',
        };
    }
}
