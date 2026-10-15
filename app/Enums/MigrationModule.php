<?php

namespace App\Enums;

use App\Actions\Migrations\Importers\ClientsImporter;
use App\Actions\Migrations\Importers\CompanyImporter;
use App\Actions\Migrations\Importers\ContractsImporter;
use App\Actions\Migrations\Importers\LegacyImporter;
use App\Actions\Migrations\Importers\RolesImporter;
use App\Actions\Migrations\Importers\RoutesImporter;
use App\Actions\Migrations\Importers\ThirdPartiesImporter;
use App\Actions\Migrations\Importers\UsersImporter;

/**
 * Módulos que se pueden migrar desde el legacy, cada uno con su importador. El orden de los casos
 * es el orden de ejecución: un módulo siempre va después de los que necesita.
 */
enum MigrationModule: string
{
    case Company = 'company';
    case Roles = 'roles';
    case ThirdParties = 'third_parties';
    case Clients = 'clients';
    case Routes = 'routes';
    case Contracts = 'contracts';
    case Users = 'users';

    public function label(): string
    {
        return match ($this) {
            self::Company => 'Configuración de la empresa',
            self::Roles => 'Roles y permisos',
            self::ThirdParties => 'Aliados y proveedores',
            self::Clients => 'Clientes',
            self::Routes => 'Rutas',
            self::Contracts => 'Contratos',
            self::Users => 'Usuarios',
        };
    }

    /**
     * @return list<self>
     */
    public function dependsOn(): array
    {
        return match ($this) {
            self::Company, self::Roles, self::ThirdParties, self::Routes => [],
            self::Clients => [self::ThirdParties],
            self::Contracts => [self::Clients],
            self::Users => [self::Roles],
        };
    }

    /**
     * @return class-string<LegacyImporter>
     */
    public function importer(): string
    {
        return match ($this) {
            self::Company => CompanyImporter::class,
            self::Roles => RolesImporter::class,
            self::ThirdParties => ThirdPartiesImporter::class,
            self::Clients => ClientsImporter::class,
            self::Routes => RoutesImporter::class,
            self::Contracts => ContractsImporter::class,
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
