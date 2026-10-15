<?php

use App\Enums\BaseRole;
use App\Enums\Permission;
use App\Models\Part;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePart;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->withRole(BaseRole::Administrator)->create());
});

test('lists the catalog with the active vehicles that have each part and their kilometers', function () {
    $oil = Part::factory()->create(['name' => 'Aceite']);
    Part::factory()->create(['name' => 'Frenos']);
    VehiclePart::factory()->for($oil)->create(['maintenance_interval_km' => 5000]);
    VehiclePart::factory()->for($oil)->create(['maintenance_interval_km' => 10000]);
    VehiclePart::factory()->for($oil)->for(Vehicle::factory()->inactive())->create(['maintenance_interval_km' => 90000]);

    $this->get(route('parts.index'))->assertInertia(fn (Assert $page) => $page
        ->component('parts/Index')
        ->where('parts.data.0.name', 'Aceite')
        ->where('parts.data.0.vehicles_count', 2)
        ->where('parts.data.0.average_interval_km', 7500)
        ->where('parts.data.0.min_interval_km', 5000)
        ->where('parts.data.0.max_interval_km', 10000)
        ->where('parts.data.1.vehicles_count', 0)
    );
});

test('creates a part in the catalog and in the chosen active vehicles', function () {
    [$first, $second] = Vehicle::factory()->count(2)->create();

    $this->post(route('parts.store'), ['name' => '  Filtro   de combustible ', 'maintenance_interval_km' => 15000, 'vehicles' => [$first->id, $second->id]])
        ->assertSessionHas('success', 'Parte "Filtro de combustible" creada en 2 vehículo(s).');

    $part = Part::query()->sole();
    expect($part->name)->toBe('Filtro de combustible')
        ->and($part->vehicleParts()->pluck('maintenance_interval_km', 'vehicle_id')->all())->toBe([$first->id => 15000, $second->id => 15000]);
});

test('validates a new part like the legacy', function () {
    Part::factory()->create(['name' => 'Aceite']);
    $inactive = Vehicle::factory()->inactive()->create();

    $this->post(route('parts.store'), ['name' => ' ACEITE ', 'maintenance_interval_km' => 5000, 'vehicles' => [Vehicle::factory()->create()->id]])
        ->assertSessionHasErrors(['name' => 'La parte "Aceite" ya existe: agrégala a más vehículos desde su ficha.']);
    $this->post(route('parts.store'), ['name' => 'Aceite #2', 'maintenance_interval_km' => 0, 'vehicles' => []])
        ->assertSessionHasErrors([
            'name' => 'El nombre de la parte solo puede tener letras, números y espacios.',
            'maintenance_interval_km',
            'vehicles' => 'Selecciona al menos un vehículo.',
        ]);
    $this->post(route('parts.store'), ['name' => 'Frenos', 'maintenance_interval_km' => 5000, 'vehicles' => [$inactive->id]])
        ->assertSessionHasErrors(['vehicles.0' => 'Algún vehículo seleccionado no existe o está inactivo.']);
});

test('the part page lists every active vehicle with or without the part', function () {
    $part = Part::factory()->create();
    VehiclePart::factory()->for($part)->for(Vehicle::factory()->state(['plate' => 'AAA111']))->create(['maintenance_interval_km' => 8000]);
    Vehicle::factory()->create(['plate' => 'BBB222']);
    Vehicle::factory()->inactive()->create();

    $this->get(route('parts.show', $part))->assertInertia(fn (Assert $page) => $page
        ->component('parts/Show')
        ->has('vehicles', 2)
        ->where('vehicles.0.plate', 'AAA111')
        ->where('vehicles.0.assignment.maintenance_interval_km', 8000)
        ->where('vehicles.1.plate', 'BBB222')
        ->where('vehicles.1.assignment', null)
    );
});

test('adds, changes and removes the part in one vehicle', function () {
    $part = Part::factory()->create(['name' => 'Frenos']);
    $vehicle = Vehicle::factory()->create(['plate' => 'TSX123']);

    $this->post(route('parts.vehicles.assign', $part), ['vehicle_id' => $vehicle->id, 'maintenance_interval_km' => 20000])
        ->assertSessionHas('success', 'Parte "Frenos" agregada al vehículo TSX123.');
    $vehiclePart = VehiclePart::query()->sole();

    $this->put(route('parts.vehicles.update', [$part, $vehiclePart]), ['maintenance_interval_km' => 25000])
        ->assertSessionHas('success', 'Kilometraje de "Frenos" actualizado a 25.000 km en TSX123.');
    expect($vehiclePart->refresh()->maintenance_interval_km)->toBe(25000);

    $this->delete(route('parts.vehicles.unassign', [$part, $vehiclePart]))->assertSessionHas('success', 'Parte "Frenos" quitada del vehículo TSX123.');
    expect(VehiclePart::query()->exists())->toBeFalse();
});

test('a part of another catalog entry cannot be changed through this part', function () {
    $vehiclePart = VehiclePart::factory()->create();

    $this->put(route('parts.vehicles.update', [Part::factory()->create(), $vehiclePart]), ['maintenance_interval_km' => 1])->assertNotFound();
});

test('adds the part to every active vehicle that lacks it and removes it from all', function () {
    $part = Part::factory()->create();
    $existing = VehiclePart::factory()->for($part)->create(['maintenance_interval_km' => 7000]);
    Vehicle::factory()->count(2)->create();
    $inactive = Vehicle::factory()->inactive()->create();

    $this->post(route('parts.vehicles.assign-all', $part), ['maintenance_interval_km' => 10000])
        ->assertSessionHas('success', "Parte \"{$part->name}\" agregada a 2 vehículo(s).");
    expect($existing->refresh()->maintenance_interval_km)->toBe(7000)
        ->and($part->vehicleParts()->where('vehicle_id', $inactive->id)->exists())->toBeFalse()
        ->and($part->vehicleParts()->count())->toBe(3);

    $this->post(route('parts.vehicles.assign-all', $part), ['maintenance_interval_km' => 10000])
        ->assertSessionHas('warning', 'Todos los vehículos activos ya tienen esta parte.');

    $this->delete(route('parts.vehicles.unassign-all', $part))->assertSessionHas('success', "Parte \"{$part->name}\" quitada de 3 vehículo(s).");
    expect(Part::query()->whereKey($part->id)->exists())->toBeTrue();
});

test('deleting a part removes it from the catalog and from every vehicle', function () {
    $vehiclePart = VehiclePart::factory()->create();

    $this->delete(route('parts.destroy', $vehiclePart->part))->assertRedirect(route('parts.index'));

    expect(Part::query()->exists())->toBeFalse()
        ->and(VehiclePart::query()->exists())->toBeFalse()
        ->and(Vehicle::query()->whereKey($vehiclePart->vehicle_id)->exists())->toBeTrue();
});

test('viewing the parts does not allow managing them', function () {
    Role::query()->where('base_key', BaseRole::Coordinator->value)->sole()->syncPermissions([Permission::VehiclePartsView->value]);
    $this->actingAs(User::factory()->withRole(BaseRole::Coordinator)->create());
    $part = Part::factory()->create();

    $this->get(route('parts.show', $part))->assertInertia(fn (Assert $page) => $page->where('can.manage', false));
    $this->post(route('parts.store'), ['name' => 'Motor', 'maintenance_interval_km' => 1, 'vehicles' => [Vehicle::factory()->create()->id]])->assertForbidden();
    $this->post(route('parts.vehicles.assign-all', $part), ['maintenance_interval_km' => 1])->assertForbidden();
    $this->delete(route('parts.destroy', $part))->assertForbidden();
});
