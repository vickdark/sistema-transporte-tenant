<?php

namespace App\Enums;

/**
 * Catálogo de permisos (capacidades) de la aplicación de cada empresa, con formato recurso.accion.
 * Es la única fuente de verdad: `php artisan tenants:sync-access` lo sincroniza en cada schema.
 * Cada módulo de la Fase 2 agrega aquí sus permisos.
 */
enum Permission: string
{
    case UsersView = 'users.view';
    case UsersCreate = 'users.create';
    case UsersUpdate = 'users.update';
    case UsersDelete = 'users.delete';
    case RolesView = 'roles.view';
    case RolesManage = 'roles.manage';
    case CompanyManage = 'company.manage';
    case AlliesView = 'allies.view';
    case AlliesCreate = 'allies.create';
    case AlliesUpdate = 'allies.update';
    case AlliesLink = 'allies.link';
    case SuppliersView = 'suppliers.view';
    case SuppliersCreate = 'suppliers.create';
    case SuppliersUpdate = 'suppliers.update';
    case SuppliersLink = 'suppliers.link';
    case ClientsView = 'clients.view';
    case ClientsCreate = 'clients.create';
    case ClientsUpdate = 'clients.update';
    case ClientsDeactivate = 'clients.deactivate';
    case RoutesView = 'routes.view';
    case RoutesCreate = 'routes.create';
    case RoutesUpdate = 'routes.update';
    case RoutesDeactivate = 'routes.deactivate';
    case ContractsView = 'contracts.view';
    case ContractsCreate = 'contracts.create';
    case ContractsUpdate = 'contracts.update';
    case AllyContractsView = 'ally_contracts.view';
    case AllyContractsCreate = 'ally_contracts.create';
    case AllyContractsUpdate = 'ally_contracts.update';
    case VehiclesView = 'vehicles.view';
    case VehiclesCreate = 'vehicles.create';
    case VehiclesUpdate = 'vehicles.update';
    case VehiclesDeactivate = 'vehicles.deactivate';
    case VehiclePartsView = 'vehicle_parts.view';
    case VehiclePartsManage = 'vehicle_parts.manage';
    case AgreementsView = 'agreements.view';
    case AgreementsCreate = 'agreements.create';
    case AgreementsUpdate = 'agreements.update';
    case AgreementsDelete = 'agreements.delete';

    public function label(): string
    {
        return match ($this) {
            self::UsersView, self::RolesView, self::AlliesView, self::SuppliersView, self::ClientsView, self::RoutesView, self::ContractsView, self::AllyContractsView, self::VehiclesView, self::VehiclePartsView, self::AgreementsView => 'Ver',
            self::UsersCreate, self::AlliesCreate, self::SuppliersCreate, self::ClientsCreate, self::RoutesCreate, self::ContractsCreate, self::AllyContractsCreate, self::VehiclesCreate, self::AgreementsCreate => 'Crear',
            self::UsersUpdate, self::AlliesUpdate, self::SuppliersUpdate, self::ClientsUpdate, self::RoutesUpdate, self::ContractsUpdate, self::AllyContractsUpdate, self::AgreementsUpdate => 'Editar',
            self::VehiclesUpdate => 'Editar (incluye documentos y fotos)',
            self::UsersDelete, self::AgreementsDelete => 'Eliminar',
            self::RolesManage, self::CompanyManage, self::VehiclePartsManage => 'Administrar',
            self::AlliesLink, self::SuppliersLink => 'Convertir en mixto / revertir',
            self::ClientsDeactivate, self::RoutesDeactivate, self::VehiclesDeactivate => 'Inhabilitar / habilitar',
        };
    }

    /**
     * Módulo al que pertenece, para agrupar la matriz de permisos.
     */
    public function module(): string
    {
        return match ($this) {
            self::UsersView, self::UsersCreate, self::UsersUpdate, self::UsersDelete => 'Usuarios',
            self::RolesView, self::RolesManage => 'Roles y permisos',
            self::CompanyManage => 'Configuración de la empresa',
            self::AlliesView, self::AlliesCreate, self::AlliesUpdate, self::AlliesLink => 'Aliados',
            self::SuppliersView, self::SuppliersCreate, self::SuppliersUpdate, self::SuppliersLink => 'Proveedores',
            self::ClientsView, self::ClientsCreate, self::ClientsUpdate, self::ClientsDeactivate => 'Clientes',
            self::RoutesView, self::RoutesCreate, self::RoutesUpdate, self::RoutesDeactivate => 'Rutas',
            self::ContractsView, self::ContractsCreate, self::ContractsUpdate => 'Contratos',
            self::AllyContractsView, self::AllyContractsCreate, self::AllyContractsUpdate => 'Contratos de aliados',
            self::VehiclesView, self::VehiclesCreate, self::VehiclesUpdate, self::VehiclesDeactivate => 'Vehículos',
            self::VehiclePartsView, self::VehiclePartsManage => 'Partes de vehículos',
            self::AgreementsView, self::AgreementsCreate, self::AgreementsUpdate, self::AgreementsDelete => 'Convenios',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $permission): string => $permission->value, self::cases());
    }
}
