<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
| Rutas de la aplicación central (solo dominios centrales, ver bootstrap/app.php).
| La autenticación y el dashboard pertenecen a cada tenant: routes/tenant.php.
*/

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('central.home');
