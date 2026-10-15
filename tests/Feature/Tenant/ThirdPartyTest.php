<?php

use App\Enums\BaseRole;
use App\Models\Ally;
use App\Models\Supplier;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ExportedSpreadsheet;

beforeEach(function () {
    $this->actingAs(User::factory()->withRole(BaseRole::Administrator)->create());
});

describe('list', function () {
    test('lists the suppliers with their type and their ally twin', function () {
        $ally = Ally::factory()->create(['name' => 'TRANSPORTES ALEFT']);
        Supplier::factory()->mixed($ally)->create(['name' => 'TRANSPORTES ALEFT']);
        Supplier::factory()->create(['name' => 'EDILBERTO HERRERA']);

        $this->get(route('suppliers.index'))->assertInertia(fn (Assert $page) => $page
            ->component('third-parties/Index')
            ->where('kind.singular', 'proveedor')
            ->where('rows.data.0.name', 'EDILBERTO HERRERA')
            ->where('rows.data.0.is_mixed', false)
            ->where('rows.data.1.twin.name', 'TRANSPORTES ALEFT')
            ->where('rows.data.1.twin.url', route('allies.show', $ally))
        );
    });

    test('filters the allies by type', function () {
        Supplier::factory()->mixed()->create();
        $single = Ally::factory()->create();

        $this->get(route('allies.index', ['type' => 'single']))->assertInertia(fn (Assert $page) => $page
            ->has('rows.data', 1)
            ->where('rows.data.0.id', $single->id)
        );
    });
});

describe('create and update', function () {
    test('registers an ally with only its name required and the NIT without surrounding spaces', function () {
        $this->post(route('allies.store'), ['name' => '  Muriel   Rosales ', 'nit' => ' 45502488 ', 'email' => 'Murolle@Hotmail.com'])
            ->assertRedirect(route('allies.index'))
            ->assertSessionHas('success', 'Aliado registrado correctamente.');

        expect(Ally::query()->sole()->only('name', 'nit', 'email', 'phone'))
            ->toBe(['name' => 'Muriel Rosales', 'nit' => '45502488', 'email' => 'murolle@hotmail.com', 'phone' => null]);

        $this->post(route('allies.store'), ['nit' => '1'])->assertSessionHasErrors('name');
    });

    test('rejects a name or NIT already used in the same module, but not in the other one', function () {
        Supplier::factory()->create(['name' => 'Transportes Aleft', 'nit' => '901024488']);
        Ally::factory()->create(['name' => 'Otro', 'nit' => '900111222']);

        $this->post(route('suppliers.store'), ['name' => 'TRANSPORTES ALEFT', 'nit' => '1'])
            ->assertSessionHasErrors(['name' => 'Ya existe un registro con este nombre.']);
        $this->post(route('suppliers.store'), ['name' => 'Nuevo', 'nit' => '901024488'])
            ->assertSessionHasErrors(['nit' => 'Ya existe un registro con este NIT.']);
        $this->post(route('suppliers.store'), ['name' => 'Otro', 'nit' => '900111222'])->assertSessionHasNoErrors();
    });

    test('updates a supplier keeping its own name and NIT valid', function () {
        $supplier = Supplier::factory()->create(['name' => 'Transportes Aleft', 'nit' => '901024488']);

        $this->put(route('suppliers.update', $supplier), ['name' => 'Transportes Aleft', 'nit' => '901024488', 'phone' => '6056663214'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Proveedor actualizado correctamente.');

        expect($supplier->refresh()->phone)->toBe('6056663214');
    });

    test('allies and suppliers cannot be deleted', function () {
        $ally = Ally::factory()->create();

        $this->delete("/allies/{$ally->id}")->assertMethodNotAllowed();
        expect(Ally::query()->find($ally->id))->not->toBeNull();
    });
});

describe('convert and revert', function () {
    test('converting a supplier creates its ally with the same data and makes it mixed', function () {
        $supplier = Supplier::factory()->create(['name' => 'Transportes Aleft', 'nit' => '901024488', 'phone' => '6056663214']);

        $this->post(route('suppliers.convert', $supplier))
            ->assertSessionHas('success', 'El proveedor fue convertido en aliado correctamente.');

        $ally = Ally::query()->sole();
        expect($ally->only('name', 'nit', 'phone'))->toBe(['name' => 'Transportes Aleft', 'nit' => '901024488', 'phone' => '6056663214'])
            ->and($supplier->refresh()->ally_id)->toBe($ally->id);

        $this->post(route('suppliers.convert', $supplier))->assertSessionHas('warning', 'Este proveedor ya fue convertido en aliado.');
        expect(Ally::count())->toBe(1);
    });

    test('converting reuses the unlinked twin with the same NIT and updates it', function () {
        $ally = Ally::factory()->create(['name' => 'Nombre viejo', 'nit' => '45502488', 'phone' => null]);
        $supplier = Supplier::factory()->create(['name' => 'Muriel Rosales', 'nit' => '45502488', 'phone' => '3116894076']);

        $this->post(route('suppliers.convert', $supplier))
            ->assertSessionHas('success', 'Se ha restablecido la relación con el aliado existente.');

        expect($ally->refresh()->only('name', 'phone'))->toBe(['name' => 'Muriel Rosales', 'phone' => '3116894076'])
            ->and($supplier->refresh()->ally_id)->toBe($ally->id)
            ->and(Ally::count())->toBe(1);
    });

    test('does not take the twin with the same NIT away from another mixed record', function () {
        $ally = Ally::factory()->create(['nit' => '45502488']);
        $other = Supplier::factory()->mixed($ally)->create(['nit' => '45502488']);
        $duplicate = Supplier::factory()->create(['nit' => '45502488']);

        $this->post(route('suppliers.convert', $duplicate))->assertSessionHasErrors('link');

        expect($other->refresh()->ally_id)->toBe($ally->id)
            ->and($duplicate->refresh()->ally_id)->toBeNull();
    });

    test('converting an ally creates its supplier and makes it mixed', function () {
        $ally = Ally::factory()->create(['name' => 'Santiago Pájaro', 'nit' => '1043652341']);

        $this->post(route('allies.convert', $ally))->assertSessionHas('success', 'El aliado fue convertido en proveedor correctamente.');

        expect(Supplier::query()->sole()->only('name', 'nit', 'ally_id'))->toBe(['name' => 'Santiago Pájaro', 'nit' => '1043652341', 'ally_id' => $ally->id]);
    });

    test('reverting breaks the link and keeps both records', function () {
        $supplier = Supplier::factory()->mixed()->create();
        $ally = $supplier->ally;

        $this->delete(route('allies.revert', $ally))->assertSessionHas('success', 'Se ha revertido la conversión a proveedor correctamente.');

        expect($supplier->refresh()->ally_id)->toBeNull()
            ->and(Ally::query()->find($ally->id))->not->toBeNull();
    });
});

test('exports the suppliers with their type', function () {
    Supplier::factory()->mixed()->create(['name' => 'B MIXTO', 'nit' => '2']);
    Supplier::factory()->create(['name' => 'A SENCILLO', 'nit' => '1', 'phone' => null, 'address' => null, 'email' => null]);

    $rows = ExportedSpreadsheet::rows($this->get(route('suppliers.export', ['format' => 'xlsx']))->assertOk());

    expect($rows[0])->toBe(['Nombre', 'NIT', 'Teléfono', 'Dirección', 'Correo', 'Tipo'])
        ->and($rows[1])->toBe(['A SENCILLO', '1', '', '', '', 'Sencillo'])
        ->and(end($rows[2]))->toBe('Mixto');
});

test('each action requires its permission', function () {
    $ally = Ally::factory()->create();
    $this->actingAs(User::factory()->withRole(BaseRole::Driver)->create());

    $this->get(route('allies.index'))->assertForbidden();
    $this->get(route('allies.show', $ally))->assertForbidden();
    $this->post(route('allies.store'), ['name' => 'X'])->assertForbidden();
    $this->put(route('allies.update', $ally), ['name' => 'X'])->assertForbidden();
    $this->post(route('allies.convert', $ally))->assertForbidden();

    expect(Supplier::count())->toBe(0);
});
