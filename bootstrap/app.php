<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$isCentralRequest = fn (Request $request): bool => in_array($request->getHost(), config('tenancy.central_domains'), true);

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // El panel central solo responde en el dominio central principal (el de APP_URL): registrarlo
        // en varios dominios duplicaría los nombres de ruta y route() apuntaría al último.
        // Las rutas de tenants (routes/tenant.php) las registra TenancyServiceProvider.
        using: function () {
            Route::middleware('web')
                ->domain(config('tenancy.central_domains')[0])
                ->group(base_path('routes/web.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) use ($isCentralRequest) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request): string => $isCentralRequest($request)
            ? route('central.login')
            : route('login'));

        $middleware->redirectUsersTo(fn (Request $request): string => $isCentralRequest($request)
            ? route('central.tenants.index')
            : route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
