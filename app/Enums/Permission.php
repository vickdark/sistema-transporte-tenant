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

    public function label(): string
    {
        return match ($this) {
            self::UsersView, self::RolesView, self::AlliesView, self::SuppliersView, self::ClientsView => 'Ver',
            self::UsersCreate, self::AlliesCreate, self::SuppliersCreate, self::ClientsCreate => 'Crear',
            self::UsersUpdate, self::AlliesUpdate, self::SuppliersUpdate, self::ClientsUpdate => 'Editar',
            self::UsersDelete => 'Eliminar',
            self::RolesManage, self::CompanyManage => 'Administrar',
            self::AlliesLink, self::SuppliersLink => 'Convertir en mixto / revertir',
            self::ClientsDeactivate => 'Inhabilitar / habilitar',
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
