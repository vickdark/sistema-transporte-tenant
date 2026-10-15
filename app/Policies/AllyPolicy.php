<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Ally;
use App\Models\User;

/**
 * Aliados. Como en el legacy, no se eliminan; "vincular" es convertir en mixto o revertirlo.
 */
class AllyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::AlliesView->value);
    }

    public function view(User $user, Ally $ally): bool
    {
        return $user->can(Permission::AlliesView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::AlliesCreate->value);
    }

    public function update(User $user, Ally $ally): bool
    {
        return $user->can(Permission::AlliesUpdate->value);
    }

    public function link(User $user, Ally $ally): bool
    {
        return $user->can(Permission::AlliesLink->value);
    }
}
