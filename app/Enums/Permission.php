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

    public function label(): string
    {
        return match ($this) {
            self::UsersView, self::RolesView => 'Ver',
            self::UsersCreate => 'Crear',
            self::UsersUpdate => 'Editar',
            self::UsersDelete => 'Eliminar',
            self::RolesManage => 'Administrar',
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
