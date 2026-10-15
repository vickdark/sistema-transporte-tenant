<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\CompanyProfile;
use App\Models\User;

/**
 * Configuración de la empresa: datos para documentos, habilitación del FUEC, logo y firma.
 */
class CompanyProfilePolicy
{
    public function update(User $user, CompanyProfile $profile): bool
    {
        return $user->can(Permission::CompanyManage->value);
    }
}
