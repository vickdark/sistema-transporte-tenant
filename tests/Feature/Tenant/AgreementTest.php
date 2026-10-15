<?php

use App\Enums\BaseRole;
use App\Models\Agreement;
use App\Models\User;
use App\Models\Vehicle;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->withRole(BaseRole::Administrator)->create());
});

test('lists the agreements with their vehicles', function () {
    $agreement = Agreement::factory()->create(['business_name' => 'DUBAI CLASS SAS']);
    Vehicle::factory()->create(['plate' => 'BBB222', 'agreement_id' => $agreement->id]);
    Vehicle::factory()->create(['plate' => 'AAA111', 'agreement_id' => $agreement->id]);

    $this->get(route('agreements.index', ['search' => 'bbb222']))->assertInertia(fn (Assert $page) => $page
        ->component('agreements/Index')
        ->has('agreements.data', 1)
        ->where('agreements.data.0.vehicles_count', 2)
        ->where('agreements.data.0.plates', ['AAA111', 'BBB222'])
    );
});

test('registers an agreement with its vehicles', function () {
    [$first, $second] = Vehicle::factory()->count(2)->create();

    $this->post(route('agreements.store'), ['nit' => ' 900 941 023 ', 'business_name' => 'Dubai  Class SAS', 'vehicles' => [$first->id, $second->id]])
        ->assertRedirect(route('agreements.index'))
        ->assertSessionHas('success', 'Convenio registrado correctamente.');

    $agreement = Agreement::query()->sole();
    expect($agreement->only('nit', 'business_name'))->toBe(['nit' => '900941023', 'business_name' => 'Dubai Class SAS'])
        ->and($agreement->vehicles()->pluck('id')->sort()->values()->all())->toBe([$first->id, $second->id]);
});

test('validates the agreement like the legacy and one agreement per vehicle', function () {
    $other = Agreement::factory()->create(['nit' => '900941023', 'business_name' => 'LINEAS PREMIUM SAS']);
    $taken = Vehicle::factory()->create(['plate' => 'TSX123', 'agreement_id' => $other->id]);
    $inactive = Vehicle::factory()->inactive()->create();

    $this->post(route('agreements.store'), [])->assertSessionHasErrors(['nit', 'business_name', 'vehicles' => 'Selecciona al menos un vehículo.']);
    $this->post(route('agreements.store'), ['nit' => '900941023', 'business_name' => 'X', 'vehicles' => [Vehicle::factory()->create()->id]])
        ->assertSessionHasErrors(['nit' => 'Ya existe un convenio con este NIT.']);
    $this->post(route('agreements.store'), ['nit' => '1', 'business_name' => 'X', 'vehicles' => [$taken->id]])
        ->assertSessionHasErrors(['vehicles.0' => 'El vehículo TSX123 ya está en el convenio con LINEAS PREMIUM SAS.']);
    $this->post(route('agreements.store'), ['nit' => '1', 'business_name' => 'X', 'vehicles' => [$inactive->id]])
        ->assertSessionHasErrors(['vehicles.0' => 'Algún vehículo seleccionado no existe o está inactivo.']);
});

test('editing releases the vehicles removed and keeps an inactive one already in the agreement', function () {
    $agreement = Agreement::factory()->create();
    $kept = Vehicle::factory()->inactive()->create(['agreement_id' => $agreement->id]);
    $removed = Vehicle::factory()->create(['agreement_id' => $agreement->id]);
    $added = Vehicle::factory()->create();

    $this->put(route('agreements.update', $agreement), ['nit' => $agreement->nit, 'business_name' => 'Nuevo nombre', 'vehicles' => [$kept->id, $added->id]])
        ->assertSessionHasNoErrors();

    expect($kept->refresh()->agreement_id)->toBe($agreement->id)
        ->and($added->refresh()->agreement_id)->toBe($agreement->id)
        ->and($removed->refresh()->agreement_id)->toBeNull();
});

test('deleting an agreement leaves its vehicles without agreement', function () {
    $agreement = Agreement::factory()->create();
    $vehicle = Vehicle::factory()->create(['agreement_id' => $agreement->id]);

    $this->delete(route('agreements.destroy', $agreement))
        ->assertRedirect(route('agreements.index'))
        ->assertSessionHas('success', 'Convenio eliminado: sus vehículos quedaron sin convenio.');

    expect(Agreement::query()->exists())->toBeFalse()
        ->and($vehicle->refresh()->agreement_id)->toBeNull();
});

test('searches active vehicles and points out the ones already in an agreement', function () {
    $agreement = Agreement::factory()->create(['business_name' => 'DUBAI CLASS SAS']);
    Vehicle::factory()->create(['plate' => 'TSX123', 'brand' => 'TOYOTA', 'line' => 'HIACE', 'model_year' => 2019, 'agreement_id' => $agreement->id]);
    Vehicle::factory()->inactive()->create(['plate' => 'TSX124']);

    $this->getJson(route('agreements.search-vehicles', ['search' => 'tsx']))
        ->assertExactJson([[
            'value' => Vehicle::query()->where('plate', 'TSX123')->value('id'),
            'label' => 'TSX123',
            'description' => 'TOYOTA HIACE 2019 · ya en convenio con DUBAI CLASS SAS',
        ]]);
});

test('each action requires its permission', function () {
    $agreement = Agreement::factory()->create();
    $this->actingAs(User::factory()->withRole(BaseRole::Driver)->create());

    $this->get(route('agreements.index'))->assertForbidden();
    $this->post(route('agreements.store'), ['nit' => '1', 'business_name' => 'X', 'vehicles' => []])->assertForbidden();
    $this->put(route('agreements.update', $agreement), [])->assertForbidden();
    $this->delete(route('agreements.destroy', $agreement))->assertForbidden();
    $this->getJson(route('agreements.search-vehicles'))->assertForbidden();
});
