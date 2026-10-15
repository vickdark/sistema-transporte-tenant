<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Aceptación de la invitación enviada al crear un usuario: el usuario define su contraseña
 * y entra al sistema. El enlace llega firmado (ver TenantUserInvitation).
 */
class InvitationController extends Controller
{
    /**
     * Show the page where the invited user sets their password.
     */
    public function show(Request $request, User $user): Response
    {
        $this->ensureInvitationIsUnused($request, $user);

        return Inertia::render('auth/AcceptInvitation', [
            'name' => $user->name,
            'email' => $user->email,
            'submitUrl' => $request->getRequestUri(),
        ]);
    }

    /**
     * Set the invited user's password and log them in.
     */
    public function store(Request $request, User $user): RedirectResponse
    {
        $this->ensureInvitationIsUnused($request, $user);

        $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user->forceFill([
            'password' => $request->password,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'invitation_accepted_at' => now(),
            'remember_token' => Str::random(60),
        ])->save();

        event(new PasswordReset($user));

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('dashboard');
    }

    /**
     * El enlace es de esta empresa (los IDs de usuario se repiten entre empresas) y la
     * contraseña del usuario no ha cambiado desde que se envió.
     */
    private function ensureInvitationIsUnused(Request $request, User $user): void
    {
        abort_unless($request->query('tenant') === tenant()->getTenantKey(), 404);

        abort_unless(
            hash_equals($user->invitationHash(), (string) $request->query('hash')),
            403,
            'Esta invitación ya fue usada o no es válida.',
        );
    }
}
