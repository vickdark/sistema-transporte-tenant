<?php

namespace App\Enums;

/**
 * Estado de una corrida de migración y de cada uno de sus pasos.
 */
enum MigrationRunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Running => 'En curso',
            self::Completed => 'Terminada',
            self::Failed => 'Fallida',
            self::Cancelled => 'Cancelada',
        };
    }
}
