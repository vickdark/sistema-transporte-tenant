<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Vehicle;

/**
 * Vehículos, con sus documentos y fotos (los gestiona quien puede editar el vehículo). Además del
 * permiso, un usuario de un aliado o de un proveedor solo trabaja con los vehículos de ese
 * tercero. No se eliminan: se inhabilitan.
 */
class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::VehiclesView->value);
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $user->can(Permission::VehiclesView->value) && $vehicle->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::VehiclesCreate->value);
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->can(Permission::VehiclesUpdate->value) && $vehicle->isVisibleTo($user);
    }

    /**
     * Inhabilitar y volver a habilitar.
     */
    public function toggleActive(User $user, Vehicle $vehicle): bool
    {
        return $user->can(Permission::VehiclesDeactivate->value) && $vehicle->isVisibleTo($user);
    }
}
