<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\TransportRoute;
use App\Models\User;

/**
 * Rutas. Como no se eliminan, "inhabilitar" las pasa a inactivas y "habilitar" las devuelve.
 */
class TransportRoutePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::RoutesView->value);
    }

    public function view(User $user, TransportRoute $route): bool
    {
        return $user->can(Permission::RoutesView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::RoutesCreate->value);
    }

    public function update(User $user, TransportRoute $route): bool
    {
        return $user->can(Permission::RoutesUpdate->value);
    }

    public function toggleActive(User $user, TransportRoute $route): bool
    {
        return $user->can(Permission::RoutesDeactivate->value);
    }
}
