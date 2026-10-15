<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un usuario sin rol no puede usar la aplicación de la empresa: ve la página "sin acceso asignado"
 * hasta que un administrador le asigne uno. Su perfil y el cierre de sesión siguen disponibles.
 */
class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->roles()->doesntExist()) {
            return Inertia::render('NoAccess')
                ->toResponse($request)
                ->setStatusCode(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
