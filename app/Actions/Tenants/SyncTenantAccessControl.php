<?php

namespace App\Actions\Tenants;

use App\Enums\BaseRole;
use App\Enums\Permission as PermissionCatalog;
use App\Models\Role;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sincroniza el schema de la empresa activa con el catálogo: crea los permisos nuevos, borra los
 * que salieron del catálogo, crea los roles base que falten y le da al Administrador todos los
 * permisos. Es idempotente: corre al crear la empresa y en cada despliegue (tenants:sync-access).
 */
class SyncTenantAccessControl
{
    public function handle(): void
    {
        $guard = User::GUARD;

        foreach (PermissionCatalog::values() as $name) {
            Permission::findOrCreate($name, $guard);
        }

        Permission::query()->whereNotIn('name', PermissionCatalog::values())->delete();

        foreach (BaseRole::cases() as $baseRole) {
            Role::query()->firstOrCreate(
                ['base_key' => $baseRole->value],
                ['name' => $baseRole->defaultName(), 'guard_name' => $guard, 'is_protected' => $baseRole->isProtected()],
            );
        }

        Role::administrator()->syncPermissions(PermissionCatalog::values());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
