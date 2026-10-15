<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Agreement;
use App\Models\User;

/**
 * Convenios. Como en el legacy se pueden eliminar: sus vehículos quedan sin convenio.
 */
class AgreementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::AgreementsView->value);
    }

    public function view(User $user, Agreement $agreement): bool
    {
        return $user->can(Permission::AgreementsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::AgreementsCreate->value);
    }

    public function update(User $user, Agreement $agreement): bool
    {
        return $user->can(Permission::AgreementsUpdate->value);
    }

    public function delete(User $user, Agreement $agreement): bool
    {
        return $user->can(Permission::AgreementsDelete->value);
    }
}
