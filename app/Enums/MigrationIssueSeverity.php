<?php

namespace App\Enums;

enum MigrationIssueSeverity: string
{
    case Quarantine = 'quarantine';
    case Error = 'error';
    case Warning = 'warning';

    public function label(): string
    {
        return match ($this) {
            self::Quarantine => 'Cuarentena',
            self::Error => 'Error',
            self::Warning => 'Advertencia',
        };
    }
}
