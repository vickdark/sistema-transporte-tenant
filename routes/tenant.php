<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\InitializeTenancyBySubdomainOfBaseDomain;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Rutas de cada empresa de transporte, identificada por su subdominio
| ({subdominio}.{tenancy.tenant_base_domain}, p. ej. demo.test).
| Los usuarios pertenecen a un tenant, por eso la autenticación vive aquí.
| Un tenant suspendido no puede usar ninguna de estas rutas.
|
*/

Route::middleware([
    'web',
    InitializeTenancyBySubdomainOfBaseDomain::class,
    PreventAccessFromCentralDomains::class,
    EnsureTenantIsActive::class,
])->group(function () {
    Route::get('/', function () {
        return Inertia::render('Welcome');
    })->name('home');

    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->middleware(['auth', 'verified'])->name('dashboard');

    require __DIR__.'/settings.php';
    require __DIR__.'/auth.php';
});
