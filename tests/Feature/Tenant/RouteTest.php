<?php

use App\Enums\BaseRole;
use App\Enums\RouteStatus;
use App\Models\TransportRoute;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->withRole(BaseRole::Administrator)->create());
});

/**
 * @return array<string, string>
 */
function routePayload(array $overrides = []): array
{
    return [
        'start_point' => '  Bocagrande ',
        'end_point' => 'Turbaco',
        'description' => 'Ruta diaria de personal',
        'type' => 'urban',
        'status' => 'active',
        ...$overrides,
    ];
}

test('lists active and maintenance routes apart from the inactive ones', function () {
    TransportRoute::factory()->create(['start_point' => 'Centro', 'end_point' => 'Villaestrella']);
    TransportRoute::factory()->create(['status' => RouteStatus::Maintenance]);
    $inactive = TransportRoute::factory()->inactive()->create();

    $this->get(route('routes.index'))->assertInertia(fn (Assert $page) => $page
        ->component('routes/Index')
        ->has('routes.data', 2)
    );

    $this->get(route('routes.index', ['status' => 'inactive']))->assertInertia(fn (Assert $page) => $page
        ->has('routes.data', 1)
        ->where('routes.data.0.id', $inactive->id)
    );
});

test('creates a route named after its start and end points', function () {
    $this->post(route('routes.store'), routePayload())
        ->assertRedirect(route('routes.index'))
        ->assertSessionHas('success', 'Ruta registrada correctamente.');

    expect(TransportRoute::query()->sole()->name)->toBe('Bocagrande - Turbaco');
});

test('all the fields are required', function () {
    $this->post(route('routes.store'), ['type' => 'flying'])
        ->assertSessionHasErrors(['start_point', 'end_point', 'description', 'type', 'status']);
});

test('rejects a route with the same start and end ignoring case and spaces', function () {
    TransportRoute::factory()->create(['start_point' => 'Bocagrande', 'end_point' => 'Turbaco']);

    $this->post(route('routes.store'), routePayload(['start_point' => 'BOCAGRANDE', 'end_point' => '  turbaco ']))
        ->assertSessionHasErrors(['end_point' => 'Ya existe la ruta "Bocagrande - Turbaco" con el mismo inicio y fin.']);
    $this->post(route('routes.store'), routePayload(['end_point' => 'Turbaco Centro']))->assertSessionHasNoErrors();
});

test('the status can be changed when editing', function () {
    $route = TransportRoute::factory()->create(['start_point' => 'Bocagrande', 'end_point' => 'Turbaco']);

    $this->put(route('routes.update', $route), routePayload(['status' => 'maintenance']))->assertSessionHasNoErrors();

    expect($route->refresh()->status)->toBe(RouteStatus::Maintenance);
});

test('deactivating sends the route to the inactive list and activating brings it back; routes cannot be deleted', function () {
    $route = TransportRoute::factory()->create();

    $this->post(route('routes.deactivate', $route))->assertSessionHas('success', 'Ruta inhabilitada: pasó a Inactivas.');
    expect($route->refresh()->status)->toBe(RouteStatus::Inactive);

    $this->post(route('routes.activate', $route))->assertSessionHas('success', 'Ruta habilitada.');
    expect($route->refresh()->status)->toBe(RouteStatus::Active);

    $this->delete("/routes/{$route->id}")->assertMethodNotAllowed();
});

test('each action requires its permission', function () {
    $route = TransportRoute::factory()->create();
    $this->actingAs(User::factory()->withRole(BaseRole::Driver)->create());

    $this->get(route('routes.index'))->assertForbidden();
    $this->post(route('routes.store'), routePayload())->assertForbidden();
    $this->put(route('routes.update', $route), routePayload())->assertForbidden();
    $this->post(route('routes.deactivate', $route))->assertForbidden();
});
