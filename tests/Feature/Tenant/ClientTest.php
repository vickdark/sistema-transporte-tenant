<?php

use App\Enums\BaseRole;
use App\Enums\ClientType;
use App\Enums\Permission;
use App\Models\Ally;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ExportedSpreadsheet;

beforeEach(function () {
    $this->actingAs(User::factory()->withRole(BaseRole::Administrator)->create());
});

/**
 * Usuario de un aliado con los permisos de clientes (como el rol Aliado del legacy).
 */
function allyUser(Ally $ally): User
{
    $role = Role::query()->where('base_key', BaseRole::Ally->value)->sole();
    $role->syncPermissions([Permission::ClientsView->value, Permission::ClientsCreate->value, Permission::ClientsUpdate->value, Permission::ClientsDeactivate->value]);

    return User::factory()->withRole(BaseRole::Ally)->create(['ally_id' => $ally->id]);
}

/**
 * @return array<string, string>
 */
function personPayload(array $overrides = []): array
{
    return [
        'type' => 'person',
        'identification_type' => 'cc',
        'identification' => '1043652341',
        'first_names' => 'Santiago',
        'last_names' => 'Pájaro',
        'city' => 'Cartagena',
        'address' => 'Vista Hermosa Mz R Lt 8',
        'phone' => '+57 313 509 5874',
        'email' => 'Santiago@Correo.test',
        ...$overrides,
    ];
}

describe('visibility', function () {
    test('internal users only see the company clients and ally users only their own', function () {
        $ally = Ally::factory()->create();
        Client::factory()->create(['first_names' => 'DE LA', 'last_names' => 'EMPRESA']);
        Client::factory()->ownedBy($ally)->create(['first_names' => 'DEL', 'last_names' => 'ALIADO']);
        Client::factory()->ownedBy(Ally::factory()->create())->create(['first_names' => 'DE OTRO', 'last_names' => 'ALIADO']);

        $this->get(route('clients.index'))->assertInertia(fn (Assert $page) => $page
            ->has('clients.data', 1)
            ->where('clients.data.0.name', 'DE LA EMPRESA')
        );

        $this->actingAs(allyUser($ally))->get(route('clients.index'))->assertInertia(fn (Assert $page) => $page
            ->has('clients.data', 1)
            ->where('clients.data.0.name', 'DEL ALIADO')
        );
    });

    test('inactive clients are listed apart and also limited to the same owner', function () {
        $ally = Ally::factory()->create();
        $mine = Client::factory()->ownedBy($ally)->inactive()->create();
        Client::factory()->inactive()->create();
        Client::factory()->ownedBy($ally)->create();

        $this->actingAs(allyUser($ally))->get(route('clients.index', ['status' => 'inactive']))->assertInertia(fn (Assert $page) => $page
            ->has('clients.data', 1)
            ->where('clients.data.0.id', $mine->id)
            ->where('query.status', 'inactive')
        );
    });

    test('a client of another owner cannot be opened, edited or deactivated', function () {
        $foreign = Client::factory()->ownedBy(Ally::factory()->create())->create();

        $this->get(route('clients.show', $foreign))->assertForbidden();
        $this->put(route('clients.update', $foreign), personPayload())->assertForbidden();
        $this->post(route('clients.deactivate', $foreign))->assertForbidden();
    });
});

describe('create and update', function () {
    test('creates a person client for the owner of whoever creates it, with a clean phone', function () {
        $ally = Ally::factory()->create();

        $this->actingAs(allyUser($ally))->post(route('clients.store'), personPayload([
            'business_name' => 'No aplica',
        ]))->assertRedirect(route('clients.index'))->assertSessionHas('success', 'Cliente registrado correctamente.');

        $client = Client::query()->sole();
        expect($client->only('type', 'phone', 'ally_id', 'business_name', 'is_active'))
            ->toBe(['type' => ClientType::Person, 'phone' => '3135095874', 'ally_id' => $ally->id, 'business_name' => null, 'is_active' => true]);
    });

    test('applies the legacy validations when creating', function () {
        $this->post(route('clients.store'), personPayload([
            'identification' => '12A',
            'phone' => '123',
            'identification_type' => 'nit',
            'first_names' => '',
        ]))->assertSessionHasErrors([
            'identification' => 'La identificación debe contener solo números (6 a 20 dígitos).',
            'phone' => 'El teléfono debe contener solo números (7 a 15 dígitos).',
            'first_names' => 'Los nombres son obligatorios para una persona.',
        ]);

        $this->post(route('clients.store'), personPayload(['type' => 'company', 'identification_type' => 'nit', 'check_digit' => '5']))
            ->assertSessionHasErrors(['business_name' => 'La razón social es obligatoria para una empresa.']);
    });

    test('the check digit is optional, also for a NIT, but must have one or two digits', function () {
        $company = ['type' => 'company', 'identification_type' => 'nit', 'identification' => '900980138', 'business_name' => 'Transportes Kemuel SAS'];

        $this->post(route('clients.store'), personPayload([...$company, 'check_digit' => 'AB1']))->assertSessionHasErrors('check_digit');
        $this->post(route('clients.store'), personPayload($company))->assertSessionHasNoErrors();

        expect(Client::query()->sole()->only('check_digit', 'first_names', 'business_name'))
            ->toBe(['check_digit' => null, 'first_names' => null, 'business_name' => 'Transportes Kemuel SAS']);
    });

    test('the identification cannot repeat any other client, even of another owner', function () {
        Client::factory()->ownedBy(Ally::factory()->create())->create(['identification' => '1043652341']);

        $this->post(route('clients.store'), personPayload())
            ->assertSessionHasErrors(['identification' => 'La identificación ya está registrada en otro cliente.']);
    });

    test('an already repeated client can still be edited while its identification does not change', function () {
        Client::factory()->create(['identification' => '830085844']);
        $repeated = Client::factory()->create(['identification' => '830085844']);

        $this->put(route('clients.update', $repeated), personPayload(['identification' => '830085844', 'city' => 'Turbaco']))
            ->assertSessionHasNoErrors();

        expect($repeated->refresh()->city)->toBe('Turbaco');
    });

    test('the email is required, but a migrated client without email can still be edited', function () {
        $this->post(route('clients.store'), personPayload(['email' => '']))->assertSessionHasErrors('email');
        $this->post(route('clients.store'), personPayload())->assertSessionHasNoErrors();
        expect(Client::query()->sole()->email)->toBe('santiago@correo.test');

        $migrated = Client::factory()->create(['email' => null, 'identification' => '830085844']);
        $migrated->forceFill(['legacy_source' => 'clientes', 'legacy_id' => '1'])->save();
        $created = Client::query()->where('identification', '1043652341')->sole();

        $this->put(route('clients.update', $migrated), personPayload(['identification' => '830085844', 'email' => '']))->assertSessionHasNoErrors();
        $this->put(route('clients.update', $created), personPayload(['email' => '']))->assertSessionHasErrors('email');
    });

    test('the client type does not change when editing', function () {
        $client = Client::factory()->create();

        $this->put(route('clients.update', $client), personPayload(['type' => 'company', 'identification' => $client->identification]));

        expect($client->refresh()->type)->toBe(ClientType::Person);
    });
});

test('deactivates and activates a client; clients cannot be deleted', function () {
    $client = Client::factory()->create();

    $this->post(route('clients.deactivate', $client))->assertSessionHas('success', 'Cliente inhabilitado correctamente.');
    expect($client->refresh()->is_active)->toBeFalse();

    $this->post(route('clients.activate', $client))->assertSessionHas('success', 'Cliente habilitado correctamente.');
    expect($client->refresh()->is_active)->toBeTrue();

    $this->delete("/clients/{$client->id}")->assertMethodNotAllowed();
});

test('exports only the clients the user can see', function () {
    Client::factory()->create(['first_names' => 'VISIBLE', 'last_names' => 'UNO']);
    Client::factory()->ownedBy(Ally::factory()->create())->create();

    $rows = ExportedSpreadsheet::rows($this->get(route('clients.export', ['format' => 'xlsx']))->assertOk());

    expect($rows)->toHaveCount(2)
        ->and($rows[1][5])->toBe('VISIBLE UNO');
});

test('each action requires its permission', function () {
    $client = Client::factory()->create();
    $this->actingAs(User::factory()->withRole(BaseRole::Driver)->create());

    $this->get(route('clients.index'))->assertForbidden();
    $this->post(route('clients.store'), personPayload())->assertForbidden();
    $this->put(route('clients.update', $client), personPayload())->assertForbidden();
    $this->post(route('clients.deactivate', $client))->assertForbidden();
});
