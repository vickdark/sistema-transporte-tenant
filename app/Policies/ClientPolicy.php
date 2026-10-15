<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Client;
use App\Models\User;

/**
 * Clientes. Además del permiso, el cliente debe ser del mismo dueño que el usuario (su aliado o
 * la empresa): nadie ve ni toca clientes ajenos. Como en el legacy, no se eliminan.
 */
class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ClientsView->value);
    }

    public function view(User $user, Client $client): bool
    {
        return $user->can(Permission::ClientsView->value) && $client->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ClientsCreate->value);
    }

    public function update(User $user, Client $client): bool
    {
        return $user->can(Permission::ClientsUpdate->value) && $client->isVisibleTo($user);
    }

    /**
     * Inhabilitar y volver a habilitar.
     */
    public function toggleActive(User $user, Client $client): bool
    {
        return $user->can(Permission::ClientsDeactivate->value) && $client->isVisibleTo($user);
    }
}
