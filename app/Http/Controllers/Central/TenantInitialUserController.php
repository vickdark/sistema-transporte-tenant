<?php

namespace App\Http\Controllers\Central;

use App\Actions\Tenants\SendTenantUserInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\UpdateTenantInitialUserRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

/**
 * Administrador inicial de una empresa, gestionado desde el panel central. El usuario vive en el
 * schema de la empresa, por eso todo se hace dentro de $tenant->run().
 */
class TenantInitialUserController extends Controller
{
    /**
     * Actualiza nombre y correo, y opcionalmente define una contraseña nueva o le envía una
     * invitación para que la defina él (p. ej. después de cambiarle el correo).
     */
    public function update(UpdateTenantInitialUserRequest $request, Tenant $tenant, SendTenantUserInvitation $sendInvitation): RedirectResponse
    {
        $user = $tenant->initialUser();

        abort_if($user === null, 404);

        $sendsInvitation = $request->boolean('send_invitation');
        $password = $sendsInvitation ? null : $request->validated('password');

        $tenant->run(function () use ($request, $user, $password): void {
            $user->fill($request->safe()->only(['name', 'email']));

            // Un correo nuevo queda sin verificar, salvo que el panel defina la contraseña ahora.
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            if ($password !== null) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                    // La contraseña la definió el panel: ya no hay una invitación pendiente.
                    'invited_at' => null,
                ]);
            }

            $user->save();
        });

        if ($sendsInvitation) {
            $sendInvitation->handle($tenant, $user);

            return back()->with('success', "Usuario inicial actualizado. Se envió la invitación a {$user->email}.");
        }

        return back()->with('success', $password !== null
            ? 'Usuario inicial actualizado con la nueva contraseña.'
            : 'Usuario inicial actualizado.');
    }
}
