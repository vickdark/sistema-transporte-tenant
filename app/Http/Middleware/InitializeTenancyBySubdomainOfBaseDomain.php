<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Symfony\Component\HttpFoundation\Response;

/**
 * Identifica al tenant por la primera parte del host sobre el dominio base de los tenants
 * (`tenancy.tenant_base_domain`): con base "test", demo.test → tenant con subdominio "demo".
 * En `domains.domain` solo se guarda el subdominio, así cambiar el dominio base no toca la BD.
 */
class InitializeTenancyBySubdomainOfBaseDomain extends InitializeTenancyByDomain
{
    /** @var callable|null */
    public static $onFail;

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle($request, Closure $next): mixed
    {
        $suffix = '.'.config('tenancy.tenant_base_domain');
        $subdomain = Str::of($request->getHost())->beforeLast($suffix)->value();

        abort_unless(
            Str::endsWith($request->getHost(), $suffix) && $subdomain !== '' && ! str_contains($subdomain, '.'),
            Response::HTTP_NOT_FOUND,
        );

        return $this->initializeTenancy($request, $next, $subdomain);
    }
}
