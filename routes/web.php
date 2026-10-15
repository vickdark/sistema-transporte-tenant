<?php

use App\Http\Controllers\Central\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Central\MigrationRunController;
use App\Http\Controllers\Central\MigrationsController;
use App\Http\Controllers\Central\MigrationSourceController;
use App\Http\Controllers\Central\Settings\PasswordController;
use App\Http\Controllers\Central\TenantController;
use App\Http\Controllers\Central\TenantInitialUserController;
use App\Http\Controllers\Central\TenantStatusController;
use Illuminate\Support\Facades\Route;

/*
| Panel central (landlord): solo en el dominio central, ver bootstrap/app.php.
| Usa el guard `central` (administradores de la plataforma, tabla central_users).
| La operación de cada empresa vive en routes/tenant.php.
*/

Route::name('central.')->group(function () {
    Route::middleware('guest:central')->group(function () {
        Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    });

    Route::middleware('auth:central')->group(function () {
        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

        Route::redirect('/', '/tenants')->name('home');

        Route::redirect('settings', '/settings/password');
        Route::get('settings/password', [PasswordController::class, 'edit'])->name('settings.password.edit');
        Route::put('settings/password', [PasswordController::class, 'update'])->name('settings.password.update');
        Route::inertia('settings/appearance', 'central/settings/Appearance')->name('settings.appearance');

        Route::resource('tenants', TenantController::class);
        Route::put('tenants/{tenant}/initial-user', [TenantInitialUserController::class, 'update'])->name('tenants.initial-user.update');
        Route::post('tenants/{tenant}/suspend', [TenantStatusController::class, 'suspend'])->name('tenants.suspend');
        Route::post('tenants/{tenant}/reactivate', [TenantStatusController::class, 'reactivate'])->name('tenants.reactivate');

        Route::get('migrations', MigrationsController::class)->name('migrations.index');
        Route::get('migrations/sources/create', [MigrationSourceController::class, 'create'])->name('migrations.sources.create');
        Route::post('migrations/sources', [MigrationSourceController::class, 'store'])->name('migrations.sources.store');
        Route::delete('migrations/sources/{source}', [MigrationSourceController::class, 'destroy'])->name('migrations.sources.destroy');
        Route::get('migrations/runs/{run}', [MigrationRunController::class, 'show'])->name('migrations.runs.show');
    });
});
