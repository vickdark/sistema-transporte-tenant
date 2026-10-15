<?php

namespace App\Actions\Tenants;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantUserInvitation;

/**
 * Deja la invitación del usuario pendiente (en el schema de su empresa) y le envía el enlace para
 * definir su contraseña. Sirve en el alta de la empresa y cuando el panel vuelve a invitar a un
 * usuario (p. ej. tras cambiarle el correo). El enlace deja de servir en cuanto la contraseña cambia.
 */
class SendTenantUserInvitation
{
    public function handle(Tenant $tenant, User $user): void
    {
        $tenant->run(fn () => $user->forceFill([
            'invited_at' => now(),
            'invitation_accepted_at' => null,
        ])->save());

        $user->notify(new TenantUserInvitation($tenant));
    }
}
