<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\RoleController;
use App\Http\Controllers\Tenant\UserController;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\EnsureUserHasRole;
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
    Route::redirect('/', '/dashboard')->name('home');

    // Aplicación de la empresa: requiere sesión y un rol asignado (sin rol se muestra
    // "sin acceso asignado"). Cada acción la autoriza su Policy con permisos, nunca por rol.
    Route::middleware(['auth', 'verified', EnsureUserHasRole::class])->group(function () {
        Route::get('dashboard', function () {
            return Inertia::render('Dashboard');
        })->name('dashboard');

        Route::get('users/export/{format}', [UserController::class, 'export'])->whereIn('format', ['xlsx', 'pdf'])->name('users.export');
        Route::resource('users', UserController::class)->except('show');
        Route::get('roles/export/{format}', [RoleController::class, 'export'])->whereIn('format', ['xlsx', 'pdf'])->name('roles.export');
        Route::resource('roles', RoleController::class)->except('show');
    });

    require __DIR__.'/settings.php';
    require __DIR__.'/auth.php';
});
