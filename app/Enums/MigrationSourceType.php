<?php

namespace App\Enums;

/**
 * Origen de los datos legacy. La carga de dump queda pendiente de la decisión #4 del plan.
 */
enum MigrationSourceType: string
{
    case MysqlConnection = 'mysql_connection';
    case DumpUpload = 'dump_upload';

    public function label(): string
    {
        return match ($this) {
            self::MysqlConnection => 'Conexión MySQL (solo lectura)',
            self::DumpUpload => 'Carga de dump',
        };
    }
}
