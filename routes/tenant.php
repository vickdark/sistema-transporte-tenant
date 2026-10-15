<?php

declare(strict_types=1);

use App\Enums\VehicleDocumentType;
use App\Http\Controllers\Tenant\AgreementController;
use App\Http\Controllers\Tenant\AllyController;
use App\Http\Controllers\Tenant\ClientController;
use App\Http\Controllers\Tenant\CompanyProfileController;
use App\Http\Controllers\Tenant\ContractController;
use App\Http\Controllers\Tenant\PartController;
use App\Http\Controllers\Tenant\RoleController;
use App\Http\Controllers\Tenant\RouteController;
use App\Http\Controllers\Tenant\SupplierController;
use App\Http\Controllers\Tenant\UserController;
use App\Http\Controllers\Tenant\VehicleController;
use App\Http\Controllers\Tenant\VehicleDocumentController;
use App\Http\Controllers\Tenant\VehiclePhotoController;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\InitializeTenancyBySubdomainOfBaseDomain;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
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
        Route::get('company', [CompanyProfileController::class, 'edit'])->name('company.edit');
        Route::put('company', [CompanyProfileController::class, 'update'])->name('company.update');
        Route::get('company/files/{type}', [CompanyProfileController::class, 'showFile'])->whereIn('type', ['logo', 'signature'])->name('company.files.show');
        Route::post('company/files/{type}', [CompanyProfileController::class, 'storeFile'])->whereIn('type', ['logo', 'signature'])->name('company.files.store');
        Route::delete('company/files/{type}', [CompanyProfileController::class, 'destroyFile'])->whereIn('type', ['logo', 'signature'])->name('company.files.destroy');

        // Aliados y proveedores: como en el legacy no se eliminan; "convert" los vuelve mixtos y su DELETE lo revierte.
        Route::get('allies/export/{format}', [AllyController::class, 'export'])->whereIn('format', ['xlsx', 'pdf'])->name('allies.export');
        Route::resource('allies', AllyController::class)->except('destroy');
        Route::post('allies/{ally}/convert', [AllyController::class, 'convert'])->name('allies.convert');
        Route::delete('allies/{ally}/convert', [AllyController::class, 'revert'])->name('allies.revert');
        Route::get('suppliers/export/{format}', [SupplierController::class, 'export'])->whereIn('format', ['xlsx', 'pdf'])->name('suppliers.export');
        Route::resource('suppliers', SupplierController::class)->except('destroy');
        Route::post('suppliers/{supplier}/convert', [SupplierController::class, 'convert'])->name('suppliers.convert');
        Route::delete('suppliers/{supplier}/convert', [SupplierController::class, 'revert'])->name('suppliers.revert');

        // Clientes: como en el legacy no se eliminan; se inhabilitan y se habilitan.
        Route::get('clients/export/{format}', [ClientController::class, 'export'])->whereIn('format', ['xlsx', 'pdf'])->name('clients.export');
        Route::resource('clients', ClientController::class)->except('destroy');
        Route::post('clients/{client}/deactivate', [ClientController::class, 'deactivate'])->name('clients.deactivate');
        Route::post('clients/{client}/activate', [ClientController::class, 'activate'])->name('clients.activate');

        // Rutas: no se eliminan (las órdenes las usan); se inhabilitan y se habilitan.
        Route::get('routes/export/{format}', [RouteController::class, 'export'])->whereIn('format', ['xlsx', 'pdf'])->name('routes.export');
        Route::resource('routes', RouteController::class)->except('destroy');
        Route::post('routes/{route}/deactivate', [RouteController::class, 'deactivate'])->name('routes.deactivate');
        Route::post('routes/{route}/activate', [RouteController::class, 'activate'])->name('routes.activate');

        // Contratos propios y de aliados: mismas pantallas, cada módulo con sus rutas y permisos.
        // No se eliminan. En los de aliados, un usuario de la empresa elige primero el aliado.
        Route::get('ally-contracts/select-ally', [ContractController::class, 'selectAlly'])->name('ally-contracts.select-ally');
        Route::post('ally-contracts/select-ally', [ContractController::class, 'storeAlly'])->name('ally-contracts.store-ally');
        Route::get('ally-contracts/search-allies', [ContractController::class, 'searchAllies'])->name('ally-contracts.search-allies');
        Route::delete('ally-contracts/select-ally', [ContractController::class, 'forgetAlly'])->name('ally-contracts.forget-ally');

        foreach (['contracts', 'ally-contracts'] as $prefix) {
            Route::prefix($prefix)->name("{$prefix}.")->controller(ContractController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('export/{format}', 'export')->whereIn('format', ['xlsx', 'pdf'])->name('export');
                Route::get('create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('search-clients', 'searchClients')->name('search-clients');
                Route::get('client/{client}', 'client')->name('client');
                Route::get('{contract}/edit', 'edit')->name('edit');
                Route::put('{contract}', 'update')->name('update');
            });
        }

        // Vehículos: no se eliminan (órdenes, mantenimientos y combustible los usan); se inhabilitan
        // y se habilitan. Documentos y fotos se gestionan desde su ficha.
        Route::get('vehicles/export/{format}', [VehicleController::class, 'export'])->whereIn('format', ['xlsx', 'pdf'])->name('vehicles.export');
        Route::get('vehicles/search-owners', [VehicleController::class, 'searchOwners'])->name('vehicles.search-owners');
        // Precognition: el formulario valida cada campo en vivo con las reglas de VehicleRequest.
        Route::resource('vehicles', VehicleController::class)->except('destroy')->middlewareFor(['store', 'update'], HandlePrecognitiveRequests::class);
        Route::post('vehicles/{vehicle}/deactivate', [VehicleController::class, 'deactivate'])->name('vehicles.deactivate');
        Route::post('vehicles/{vehicle}/activate', [VehicleController::class, 'activate'])->name('vehicles.activate');
        Route::scopeBindings()->group(function () {
            $documentTypes = array_column(VehicleDocumentType::cases(), 'value');
            Route::post('vehicles/{vehicle}/documents/{type}', [VehicleDocumentController::class, 'store'])->whereIn('type', $documentTypes)->name('vehicles.documents.store');
            Route::post('vehicles/{vehicle}/documents/{type}/renew', [VehicleDocumentController::class, 'renew'])->whereIn('type', $documentTypes)->name('vehicles.documents.renew');
            Route::get('vehicles/{vehicle}/documents/{document}/file', [VehicleDocumentController::class, 'file'])->name('vehicles.documents.file');
            Route::post('vehicles/{vehicle}/photos', [VehiclePhotoController::class, 'store'])->name('vehicles.photos.store');
            Route::get('vehicles/{vehicle}/photos/{photo}', [VehiclePhotoController::class, 'show'])->name('vehicles.photos.show');
            Route::delete('vehicles/{vehicle}/photos/{photo}', [VehiclePhotoController::class, 'destroy'])->name('vehicles.photos.destroy');
        });

        // Partes de vehículos: catálogo y, en la ficha de cada parte, sus vehículos.
        Route::get('parts/export/{format}', [PartController::class, 'export'])->whereIn('format', ['xlsx', 'pdf'])->name('parts.export');
        Route::resource('parts', PartController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
        Route::scopeBindings()->prefix('parts/{part}/vehicles')->name('parts.vehicles.')->controller(PartController::class)->group(function () {
            Route::post('/', 'assign')->name('assign');
            Route::post('all', 'assignAll')->name('assign-all');
            Route::delete('/', 'unassignAll')->name('unassign-all');
            Route::put('{vehiclePart}', 'updateAssignment')->name('update');
            Route::delete('{vehiclePart}', 'unassign')->name('unassign');
        });

        // Convenios: como en el legacy se eliminan (sus vehículos quedan sin convenio).
        Route::get('agreements/export/{format}', [AgreementController::class, 'export'])->whereIn('format', ['xlsx', 'pdf'])->name('agreements.export');
        Route::get('agreements/search-vehicles', [AgreementController::class, 'searchVehicles'])->name('agreements.search-vehicles');
        Route::resource('agreements', AgreementController::class);

        Route::get('roles/export/{format}', [RoleController::class, 'export'])->whereIn('format', ['xlsx', 'pdf'])->name('roles.export');
        Route::resource('roles', RoleController::class)->except('show');
    });

    require __DIR__.'/settings.php';
    require __DIR__.'/auth.php';
});
