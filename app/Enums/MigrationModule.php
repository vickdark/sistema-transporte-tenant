<?php

namespace App\Enums;

use App\Actions\Migrations\Importers\LegacyImporter;
use App\Actions\Migrations\Importers\RolesImporter;
use App\Actions\Migrations\Importers\UsersImporter;

/**
 * Módulos que se pueden migrar desde el legacy, cada uno con su importador. El orden de los casos
 * es el orden de ejecución: un módulo siempre va después de los que necesita.
 */
enum MigrationModule: string
{
    case Roles = 'roles';
    case Users = 'users';

    public function label(): string
    {
        return match ($this) {
            self::Roles => 'Roles y permisos',
            self::Users => 'Usuarios',
        };
    }

    /**
     * @return list<self>
     */
    public function dependsOn(): array
    {
        return match ($this) {
            self::Roles => [],
            self::Users => [self::Roles],
        };
    }

    /**
     * @return class-string<LegacyImporter>
     */
    public function importer(): string
    {
        return match ($this) {
            self::Roles => RolesImporter::class,
            self::Users => UsersImporter::class,
        };
    }

    /**
     * @param  list<self>  $modules
     * @return list<self>
     */
    public static function inExecutionOrder(array $modules): array
    {
        return array_values(array_filter(self::cases(), fn (self $module): bool => in_array($module, $modules, true)));
    }
}
