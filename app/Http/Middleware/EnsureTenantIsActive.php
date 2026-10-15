<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea todo acceso a un tenant suspendido sin tocar sus datos. Debe ir después de la
 * inicialización de tenancy.
 */
class EnsureTenantIsActive
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (tenant()?->isSuspended()) {
            return Inertia::render('TenantSuspended')
                ->toResponse($request)
                ->setStatusCode(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
