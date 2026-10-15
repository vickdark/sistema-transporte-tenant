<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * Gestión de usuarios de la empresa. Solo permisos; la regla "debe quedar un Administrador"
 * la aplica AssignUserRole (es una validación del estado, no una autorización).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::UsersView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::UsersCreate->value);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(Permission::UsersUpdate->value);
    }

    /**
     * Nadie se borra a sí mismo desde la gestión de usuarios.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->can(Permission::UsersDelete->value) && ! $user->is($model);
    }
}
