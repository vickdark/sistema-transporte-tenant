<?php

namespace App\Enums;

enum MigrationRunMode: string
{
    case DryRun = 'dry_run';
    case Live = 'live';

    public function label(): string
    {
        return match ($this) {
            self::DryRun => 'Simulación',
            self::Live => 'Real',
        };
    }
}
