<?php

namespace App\Enums;

/**
 * Tipo de cliente. Persona usa nombres y apellidos; empresa, razón social y nombre comercial.
 */
enum ClientType: string
{
    case Person = 'person';
    case Company = 'company';

    public function label(): string
    {
        return match ($this) {
            self::Person => 'Persona',
            self::Company => 'Empresa',
        };
    }

    /**
     * Tipo de identificación que se sugiere al elegir el tipo de cliente (se puede cambiar).
     */
    public function suggestedIdentificationType(): IdentificationType
    {
        return match ($this) {
            self::Person => IdentificationType::CitizenId,
            self::Company => IdentificationType::Nit,
        };
    }
}
