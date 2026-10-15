<?php

namespace App\Enums;

/**
 * Roles que se siembran en cada empresa. Se identifican por `roles.base_key` (no por el nombre),
 * así una empresa puede renombrarlos sin que la sincronización los vuelva a crear.
 * Solo Administrador está protegido: tiene siempre todos los permisos y no se puede borrar.
 */
enum BaseRole: string
{
    case Administrator = 'administrator';
    case Administrative = 'administrative';
    case Coordinator = 'coordinator';
    case CoordinationAssistant = 'coordination_assistant';
    case Driver = 'driver';
    case Client = 'client';
    case Supplier = 'supplier';
    case Ally = 'ally';

    public function defaultName(): string
    {
        return match ($this) {
            self::Administrator => 'Administrador',
            self::Administrative => 'Administrativo',
            self::Coordinator => 'Coordinador',
            self::CoordinationAssistant => 'Asistente de coordinación',
            self::Driver => 'Conductor',
            self::Client => 'Cliente',
            self::Supplier => 'Proveedor',
            self::Ally => 'Aliado',
        };
    }

    public function isProtected(): bool
    {
        return $this === self::Administrator;
    }
}
