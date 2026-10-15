<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;

/**
 * Gestión de roles de la empresa. El rol protegido (Administrador) no se edita ni se borra:
 * siempre tiene todos los permisos.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::RolesView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::RolesManage->value);
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can(Permission::RolesManage->value) && ! $role->is_protected;
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can(Permission::RolesManage->value) && ! $role->is_protected;
    }
}
