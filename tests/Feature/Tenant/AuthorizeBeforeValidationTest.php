<?php

use App\Enums\BaseRole;
use App\Models\Ally;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\TransportRoute;
use App\Models\User;

/*
 * Un usuario sin permiso que envía un formulario mal llenado recibe "prohibido", no los errores
 * de validación: la autorización va antes de validar en todos los formularios de la empresa.
 */

beforeEach(function () {
    $this->actingAs(User::factory()->withRole(BaseRole::Driver)->create());
});

test('forbids before validating', function (string $method, Closure $url) {
    $this->{$method}($url(), [])->assertForbidden()->assertSessionHasNoErrors();
})->with([
    'crear usuario' => ['post', fn () => route('users.store')],
    'editar usuario' => ['put', fn () => route('users.update', User::factory()->create())],
    'crear rol' => ['post', fn () => route('roles.store')],
    'editar rol' => ['put', fn () => route('roles.update', Role::query()->where('base_key', BaseRole::Coordinator->value)->sole())],
    'configuración de la empresa' => ['put', fn () => route('company.update')],
    'crear cliente' => ['post', fn () => route('clients.store')],
    'editar cliente' => ['put', fn () => route('clients.update', Client::factory()->create())],
    'crear ruta' => ['post', fn () => route('routes.store')],
    'editar ruta' => ['put', fn () => route('routes.update', TransportRoute::factory()->create())],
    'crear aliado' => ['post', fn () => route('allies.store')],
    'editar aliado' => ['put', fn () => route('allies.update', Ally::factory()->create())],
    'crear proveedor' => ['post', fn () => route('suppliers.store')],
    'editar proveedor' => ['put', fn () => route('suppliers.update', Supplier::factory()->create())],
    'crear contrato' => ['post', fn () => route('contracts.store')],
    'editar contrato' => ['put', fn () => route('contracts.update', Contract::factory()->create())],
]);
