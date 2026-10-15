<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Part;
use App\Models\User;

/**
 * Catálogo de partes y las partes de cada vehículo: ver, o administrar (crear partes, asignarlas a
 * los vehículos, cambiar su kilometraje, quitarlas y eliminar partes del catálogo).
 */
class PartPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::VehiclePartsView->value);
    }

    public function view(User $user, Part $part): bool
    {
        return $user->can(Permission::VehiclePartsView->value);
    }

    public function manage(User $user): bool
    {
        return $user->can(Permission::VehiclePartsManage->value);
    }
}
