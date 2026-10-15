<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Supplier;
use App\Models\User;

/**
 * Proveedores. Como en el legacy, no se eliminan; "vincular" es convertir en mixto o revertirlo.
 */
class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::SuppliersView->value);
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->can(Permission::SuppliersView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::SuppliersCreate->value);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->can(Permission::SuppliersUpdate->value);
    }

    public function link(User $user, Supplier $supplier): bool
    {
        return $user->can(Permission::SuppliersLink->value);
    }
}
