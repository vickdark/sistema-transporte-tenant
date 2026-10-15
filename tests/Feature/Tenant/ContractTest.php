<?php

use App\Actions\Contracts\ContractOwner;
use App\Enums\BaseRole;
use App\Enums\ContractKind;
use App\Enums\Permission;
use App\Models\Ally;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Carbon::setTestNow('2026-06-15');
    $this->actingAs(User::factory()->withRole(BaseRole::Administrator)->create());
});

afterEach(fn () => Carbon::setTestNow());

/**
 * @return array<string, mixed>
 */
function contractPayload(array $overrides = []): array
{
    return [
        'purpose' => 'business',
        'starts_on' => '2026-07-01',
        'ends_on' => '2026-12-31',
        'responsible_identification' => '73146290',
        'responsible_name' => 'Alain Rosales',
        'responsible_email' => 'alain@cliente.test',
        'responsible_phone' => '3001234567',
        ...$overrides,
    ];
}

/**
 * Usuario de un aliado con los permisos de contratos de aliados (como el rol Aliado del legacy).
 */
function allyContractsUser(Ally $ally): User
{
    Role::query()->where('base_key', BaseRole::Ally->value)->sole()->syncPermissions([
        Permission::AllyContractsView->value, Permission::AllyContractsCreate->value, Permission::AllyContractsUpdate->value,
    ]);

    return User::factory()->withRole(BaseRole::Ally)->create(['ally_id' => $ally->id]);
}

describe('own contracts', function () {
    test('lists the company clients that have contracts, with their totals', function () {
        $client = Client::factory()->create();
        Contract::factory()->for($client)->expired()->create();
        Contract::factory()->for($client)->create();
        Contract::factory()->for(Client::factory()->create())->expired()->create();

        $this->get(route('contracts.index', ['status' => 'current']))->assertInertia(fn (Assert $page) => $page
            ->component('contracts/Index')
            ->has('clients.data', 1)
            ->where('clients.data.0.id', $client->id)
            ->where('clients.data.0.contracts_count', 2)
            ->where('clients.data.0.last_ends_on', '2026-12-31')
            ->where('clients.data.0.has_current_contract', true)
        );
    });

    test('creates a contract numbered after the last one', function () {
        $client = Client::factory()->create();
        Contract::factory()->for(Client::factory()->create())->create(['number' => 403]);

        $this->post(route('contracts.store'), contractPayload(['client_id' => $client->id]))
            ->assertRedirect(route('contracts.client', $client))
            ->assertSessionHas('success', 'Contrato 0404 creado exitosamente.');

        expect(Contract::query()->where('client_id', $client->id)->sole()->only('kind', 'number', 'ally_id'))
            ->toBe(['kind' => ContractKind::Own, 'number' => 404, 'ally_id' => null]);
    });

    test('the end date cannot go past december 31 of the start year nor before the start', function () {
        $client = Client::factory()->create();

        $this->post(route('contracts.store'), contractPayload(['client_id' => $client->id, 'ends_on' => '2027-01-15']))
            ->assertSessionHasErrors(['ends_on' => 'La fecha de fin no puede superar el 31 de diciembre del año de la fecha de inicio (31/12/2026).']);
        $this->post(route('contracts.store'), contractPayload(['client_id' => $client->id, 'ends_on' => '2026-06-30']))
            ->assertSessionHasErrors(['ends_on' => 'La fecha de fin no puede ser anterior a la fecha de inicio.']);
    });

    test('a client cannot have two contracts at the same time, but can renew after the current one', function () {
        $client = Client::factory()->create();
        Contract::factory()->for($client)->create(['number' => 7, 'starts_on' => '2026-01-01', 'ends_on' => '2026-06-30']);

        $this->post(route('contracts.store'), contractPayload(['client_id' => $client->id, 'starts_on' => '2026-06-01']))
            ->assertSessionHasErrors(['ends_on' => 'El cliente ya tiene el contrato 0007 del 01/01/2026 al 30/06/2026: las fechas no pueden cruzarse.']);
        $this->post(route('contracts.store'), contractPayload(['client_id' => $client->id]))->assertSessionHasNoErrors();
    });

    test('requires an active client of the company', function () {
        $allyClient = Client::factory()->ownedBy(Ally::factory()->create())->create();
        $inactive = Client::factory()->inactive()->create();

        $this->post(route('contracts.store'), contractPayload(['client_id' => $allyClient->id]))->assertSessionHasErrors('client_id');
        $this->post(route('contracts.store'), contractPayload(['client_id' => $inactive->id]))->assertSessionHasErrors('client_id');
    });

    test('a company client requires the data of the responsible person', function () {
        $company = Client::factory()->company()->create();

        $this->post(route('contracts.store'), contractPayload(['client_id' => $company->id, 'responsible_email' => 'no-es-correo', 'responsible_name' => '']))
            ->assertSessionHasErrors(['responsible_email', 'responsible_name']);
    });

    test('for a person client the responsible is the client itself', function () {
        $person = Client::factory()->create(['first_names' => 'SANTIAGO', 'last_names' => 'PAJARO', 'identification' => '1043652341', 'phone' => '3135095874', 'email' => 'santiago@correo.test']);

        $this->post(route('contracts.store'), contractPayload([
            'client_id' => $person->id,
            'responsible_identification' => '',
            'responsible_name' => 'Otro nombre',
            'responsible_email' => '',
            'responsible_phone' => '',
        ]))->assertSessionHasNoErrors();

        expect(Contract::query()->sole()->only('responsible_identification', 'responsible_name', 'responsible_email', 'responsible_phone'))
            ->toBe(['responsible_identification' => '1043652341', 'responsible_name' => 'SANTIAGO PAJARO', 'responsible_email' => 'santiago@correo.test', 'responsible_phone' => '3135095874']);
    });

    test('a person client without email gets it asked in the contract and remembered', function () {
        $person = Client::factory()->create(['email' => null]);

        $this->post(route('contracts.store'), contractPayload(['client_id' => $person->id, 'responsible_email' => '']))
            ->assertSessionHasErrors('responsible_email');

        $this->post(route('contracts.store'), contractPayload(['client_id' => $person->id, 'responsible_email' => 'Nuevo@Correo.test']))
            ->assertSessionHasNoErrors();

        expect(Contract::query()->sole()->responsible_email)->toBe('nuevo@correo.test')
            ->and($person->refresh()->email)->toBe('nuevo@correo.test');
    });

    test('current and upcoming contracts can be edited; expired ones are read only', function () {
        $client = Client::factory()->create();
        $expired = Contract::factory()->for($client)->expired()->create();
        $upcoming = Contract::factory()->for($client)->create(['starts_on' => '2026-07-01', 'ends_on' => '2026-12-31']);

        $this->put(route('contracts.update', $upcoming), contractPayload(['purpose' => 'tourists']))->assertSessionHasNoErrors();
        expect($upcoming->refresh()->purpose->value)->toBe('tourists');

        $this->get(route('contracts.edit', $expired))->assertForbidden();
        $this->put(route('contracts.update', $expired), contractPayload(['starts_on' => '2025-01-01', 'ends_on' => '2025-12-31']))->assertForbidden();
    });

    test('ally users cannot see the company contracts', function () {
        $this->actingAs(allyContractsUser(Ally::factory()->create()));

        $this->get(route('contracts.index'))->assertForbidden();
    });
});

describe('ally contracts', function () {
    test('company users choose the ally first and then work only with its contracts', function () {
        $ally = Ally::factory()->create(['name' => 'ALIADO UNO']);
        $mine = Client::factory()->ownedBy($ally)->create();
        Contract::factory()->forAlly($mine)->create();
        Contract::factory()->forAlly(Client::factory()->ownedBy(Ally::factory()->create())->create())->create();

        $this->get(route('ally-contracts.index'))->assertRedirect(route('ally-contracts.select-ally'));

        $this->post(route('ally-contracts.store-ally'), ['ally_id' => $ally->id])
            ->assertRedirect(route('ally-contracts.index'))
            ->assertSessionHas('success', 'Gestionando información del aliado: ALIADO UNO');

        $this->get(route('ally-contracts.index'))->assertInertia(fn (Assert $page) => $page
            ->where('module.ally.name', 'ALIADO UNO')
            ->has('clients.data', 1)
            ->where('clients.data.0.id', $mine->id)
        );
    });

    test('the ally picker searches allies by name or NIT; ally users cannot use it', function () {
        $ally = Ally::factory()->create(['name' => 'TRANSPORTES ALEFT', 'nit' => '901024488']);
        Contract::factory()->forAlly(Client::factory()->ownedBy($ally)->create())->create();
        Ally::factory()->create(['name' => 'OTRO ALIADO', 'nit' => '800000001']);

        $this->getJson(route('ally-contracts.search-allies', ['search' => '9010']))
            ->assertOk()
            ->assertExactJson([['value' => $ally->id, 'label' => 'TRANSPORTES ALEFT', 'description' => 'NIT 901024488 · 1 contratos']]);

        $this->actingAs(allyContractsUser($ally))->getJson(route('ally-contracts.search-allies'))->assertForbidden();
    });

    test('a contract created for the chosen ally belongs to it and has its own numbering', function () {
        $ally = Ally::factory()->create();
        $client = Client::factory()->ownedBy($ally)->create();
        Contract::factory()->create(['number' => 900]);
        Contract::factory()->forAlly(Client::factory()->ownedBy($ally)->create())->create(['number' => 2538]);

        $this->withSession([ContractOwner::SESSION_KEY => $ally->id])
            ->post(route('ally-contracts.store'), contractPayload(['client_id' => $client->id]))
            ->assertSessionHasNoErrors();

        expect(Contract::query()->where('client_id', $client->id)->sole()->only('kind', 'number', 'ally_id'))
            ->toBe(['kind' => ContractKind::Ally, 'number' => 2539, 'ally_id' => $ally->id]);
    });

    test('ally users work directly with their own ally and cannot reach another one', function () {
        $ally = Ally::factory()->create();
        $foreign = Contract::factory()->forAlly(Client::factory()->ownedBy(Ally::factory()->create())->create())->create();
        $this->actingAs(allyContractsUser($ally));

        $this->get(route('ally-contracts.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('module.can_change_ally', false)
        );
        $this->get(route('ally-contracts.edit', $foreign))->assertNotFound();
        $this->get(route('ally-contracts.select-ally'))->assertRedirect(route('ally-contracts.index'));
    });

    test('the client search only offers active clients of the ally, with their current contract', function () {
        $ally = Ally::factory()->create();
        $client = Client::factory()->ownedBy($ally)->create(['first_names' => 'MARIA', 'last_names' => 'PEREZ']);
        Contract::factory()->forAlly($client)->create(['number' => 12]);
        Client::factory()->create(['first_names' => 'MARIA', 'last_names' => 'EMPRESA']);

        $this->withSession([ContractOwner::SESSION_KEY => $ally->id])
            ->getJson(route('ally-contracts.search-clients', ['search' => 'maria']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $client->id)
            ->assertJsonPath('0.current_contract.number', '0012');
    });
});

test('each action requires its permission', function () {
    $contract = Contract::factory()->create();
    $this->actingAs(User::factory()->withRole(BaseRole::Driver)->create());

    $this->get(route('contracts.index'))->assertForbidden();
    $this->post(route('contracts.store'), contractPayload(['client_id' => $contract->client_id]))->assertForbidden();
    $this->put(route('contracts.update', $contract), contractPayload())->assertForbidden();
    $this->get(route('ally-contracts.select-ally'))->assertForbidden();
});
