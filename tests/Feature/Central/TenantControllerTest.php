<?php

use App\Enums\TenantStatus;
use App\Models\CentralUser;
use App\Models\Domain;
use App\Models\MigrationRun;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantUserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(CentralUser::factory()->create(), 'central');
});

/**
 * @return array<string, string>
 */
function validTenantPayload(array $overrides = []): array
{
    return [
        'name' => 'Transportes Kemuel S.A.S.',
        'nit' => '900123456-7',
        'email' => 'contacto@kemuel.test',
        'phone' => '3001234567',
        'address' => 'Calle 1 # 2-3',
        'city' => 'Bogotá',
        'subdomain' => 'kemuel',
        'admin_name' => 'Ana Gerente',
        'admin_email' => 'ana@kemuel.test',
        'send_invitation' => true,
        ...$overrides,
    ];
}

describe('index', function () {
    test('lists the tenants with their subdomain', function () {
        $tenant = Tenant::factory()->create(['name' => 'Kemuel']);
        $tenant->domains()->create(['domain' => 'kemuel']);

        $this->get(route('central.tenants.index'))->assertInertia(fn (Assert $page) => $page
            ->component('central/tenants/Index')
            ->has('tenants.data', 1)
            ->where('tenants.data.0.name', 'Kemuel')
            ->where('tenants.data.0.subdomain', 'kemuel')
        );
    });

    test('filters the tenants by name, NIT and status', function (array $query, string $expected) {
        Tenant::factory()->create(['name' => 'Kemuel', 'nit' => '900111111-1']);
        Tenant::factory()->suspended()->create(['name' => 'Globex', 'nit' => '800222222-2']);

        $this->get(route('central.tenants.index', $query))->assertInertia(fn (Assert $page) => $page
            ->has('tenants.data', 1)
            ->where('tenants.data.0.name', $expected)
        );
    })->with([
        'name' => [['search' => 'kem'], 'Kemuel'],
        'nit' => [['search' => '800222'], 'Globex'],
        'status' => [['status' => 'suspended'], 'Globex'],
    ]);
});

describe('store', function () {
    test('creates the tenant with its subdomain and invites its first administrator', function () {
        Notification::fake();
        $baseDomain = config('tenancy.tenant_base_domain');

        $response = $this->post(route('central.tenants.store'), validTenantPayload());

        $tenant = Tenant::query()->where('nit', '900123456-7')->firstOrFail();
        $response->assertRedirect(route('central.tenants.show', $tenant));

        expect($tenant->name)->toBe('Transportes Kemuel S.A.S.')
            ->and($tenant->status)->toBe(TenantStatus::Active)
            ->and($tenant->subdomain->domain)->toBe('kemuel');

        $admin = $tenant->run(fn () => User::query()->where('email', 'ana@kemuel.test')->firstOrFail());
        expect($tenant->initial_user_id)->toBe($admin->id)
            ->and($admin->hasPendingInvitation())->toBeTrue();

        Notification::assertSentTo($admin, TenantUserInvitation::class, function (TenantUserInvitation $notification) use ($admin, $baseDomain) {
            return str_starts_with($notification->invitationUrl($admin), "http://kemuel.{$baseDomain}/invitation/{$admin->id}?");
        });
    });

    test('creates the administrator with the password set in the panel instead of inviting', function () {
        Notification::fake();

        $this->post(route('central.tenants.store'), validTenantPayload([
            'send_invitation' => false,
            'admin_password' => 'secreto-seguro-123',
            'admin_password_confirmation' => 'secreto-seguro-123',
        ]))->assertSessionHas('success', 'Empresa creada. El administrador ya puede ingresar con la contraseña definida.');

        $admin = Tenant::query()->sole()->initialUser();
        expect(Hash::check('secreto-seguro-123', $admin->password))->toBeTrue()
            ->and($admin->hasPendingInvitation())->toBeFalse()
            ->and($admin->email_verified_at)->not->toBeNull();
        Notification::assertNothingSent();
    });

    test('requires a confirmed password when the invitation is not sent', function () {
        $this->post(route('central.tenants.store'), validTenantPayload([
            'send_invitation' => false,
            'admin_password' => 'secreto-seguro-123',
            'admin_password_confirmation' => 'otra-cosa',
        ]))->assertSessionHasErrors(['admin_password' => 'La confirmación del campo contraseña del administrador no coincide.']);

        $this->post(route('central.tenants.store'), validTenantPayload(['send_invitation' => false]))
            ->assertSessionHasErrors(['admin_password' => 'El campo contraseña del administrador es obligatorio.']);

        expect(Tenant::count())->toBe(0);
    });

    test('creates and migrates a separate schema for the tenant', function () {
        Notification::fake();

        $this->post(route('central.tenants.store'), validTenantPayload());

        $tenant = Tenant::query()->sole();
        $schema = $tenant->database()->getName();

        expect($schema)->toBe('tenant_kemuel')
            ->and($tenant->run(fn () => Schema::hasTable('users')))->toBeTrue()
            ->and(Schema::connection('pgsql')->hasTable("{$schema}.users"))->toBeTrue()
            ->and(Schema::connection('pgsql')->hasTable('users'))->toBeFalse();
    });

    test('registers the subdomain in Herd when local auto link is enabled', function () {
        Notification::fake();
        Process::fake();
        config(['tenancy.herd.auto_link' => true]);

        $this->post(route('central.tenants.store'), validTenantPayload())
            ->assertSessionMissing('warning');

        Process::assertRan(fn ($process) => $process->command === ['herd', 'link', 'kemuel']);
    });

    test('warns how to register the subdomain when Herd fails', function () {
        Notification::fake();
        Process::fake(['*' => Process::result(exitCode: 1)]);
        config(['tenancy.herd.auto_link' => true]);

        $this->post(route('central.tenants.store'), validTenantPayload())
            ->assertSessionHas('warning', 'No se pudo registrar kemuel.'.config('tenancy.tenant_base_domain').' en Herd. Ejecuta `herd link kemuel` en la carpeta del proyecto.');

        expect(Tenant::count())->toBe(1);
    });

    test('does not call Herd when local auto link is disabled', function () {
        Notification::fake();
        Process::fake();

        $this->post(route('central.tenants.store'), validTenantPayload());

        Process::assertNothingRan();
    });

    test('requires the company, subdomain and administrator data', function () {
        $this->post(route('central.tenants.store'), [])
            ->assertSessionHasErrors(['name', 'nit', 'subdomain', 'admin_name', 'admin_email']);

        expect(Tenant::count())->toBe(0);
    });

    test('rejects a NIT that already belongs to another tenant', function () {
        Tenant::factory()->create(['nit' => '900123456-7']);

        $this->post(route('central.tenants.store'), validTenantPayload())
            ->assertSessionHasErrors(['nit' => 'El valor del campo NIT ya está en uso.']);
    });

    test('rejects an invalid NIT', function () {
        $this->post(route('central.tenants.store'), validTenantPayload(['nit' => '90A-12']))
            ->assertSessionHasErrors(['nit' => 'El formato del campo NIT no es válido.']);
    });

    test('rejects a subdomain that is already in use', function () {
        Tenant::factory()->create()->domains()->create(['domain' => 'kemuel']);

        $this->post(route('central.tenants.store'), validTenantPayload())
            ->assertSessionHasErrors(['subdomain' => 'Este subdominio ya está en uso.']);
    });

    test('rejects reserved or malformed subdomains', function (string $subdomain) {
        $this->post(route('central.tenants.store'), validTenantPayload(['subdomain' => $subdomain]))
            ->assertSessionHasErrors('subdomain');

        expect(Tenant::count())->toBe(0);
    })->with([
        'www' => ['www'],
        'admin' => ['admin'],
        'leading hyphen' => ['-kemuel'],
        'dotted' => ['kemuel.otro'],
        'spaces and uppercase' => ['Kemuel SAS'],
        'central domain host' => fn () => str(config('tenancy.central_domains')[0])->before('.')->value(),
        'longer than the schema name allows' => [str_repeat('a', 57)],
    ]);
});

describe('show', function () {
    test('renders the tenant with its URL built from the subdomain', function () {
        $tenant = Tenant::factory()->create();
        $tenant->domains()->create(['domain' => 'kemuel']);
        $baseDomain = config('tenancy.tenant_base_domain');

        $this->get(route('central.tenants.show', $tenant))->assertInertia(fn (Assert $page) => $page
            ->component('central/tenants/Show')
            ->where('tenant.id', $tenant->id)
            ->where('tenant.subdomain', 'kemuel')
            ->where('tenant.url', "http://kemuel.{$baseDomain}/")
        );
    });
});

describe('update', function () {
    test('updates the company data and the subdomain', function () {
        $tenant = Tenant::factory()->create();
        $tenant->domains()->create(['domain' => 'kemuel']);

        $this->put(route('central.tenants.update', $tenant), [
            'name' => 'Nuevo nombre',
            'nit' => '901000000-1',
            'city' => 'Medellín',
            'subdomain' => 'kemuel-sas',
        ])->assertRedirect(route('central.tenants.show', $tenant));

        expect($tenant->refresh())
            ->name->toBe('Nuevo nombre')
            ->nit->toBe('901000000-1')
            ->city->toBe('Medellín')
            ->and($tenant->domains()->pluck('domain')->all())->toBe(['kemuel-sas']);
    });

    test('keeps the tenant own NIT and subdomain valid but rejects another tenant ones', function () {
        $tenant = Tenant::factory()->create(['nit' => '900000000-1']);
        $tenant->domains()->create(['domain' => 'kemuel']);
        Tenant::factory()->create(['nit' => '800000000-2'])->domains()->create(['domain' => 'globex']);

        $this->put(route('central.tenants.update', $tenant), ['name' => 'X', 'nit' => '900000000-1', 'subdomain' => 'kemuel'])
            ->assertSessionHasNoErrors();

        $this->put(route('central.tenants.update', $tenant), ['name' => 'X', 'nit' => '800000000-2', 'subdomain' => 'globex'])
            ->assertSessionHasErrors([
                'nit' => 'El valor del campo NIT ya está en uso.',
                'subdomain' => 'Este subdominio ya está en uso.',
            ]);

        expect($tenant->domains()->pluck('domain')->all())->toBe(['kemuel']);
    });

    test('renames the tenant schema to follow the new subdomain', function () {
        $tenant = Tenant::factory()->create(['nit' => '900000000-1', 'tenancy_db_name' => 'tenant_kemuel']);
        $tenant->domains()->create(['domain' => 'kemuel']);
        $tenant->run(fn () => User::factory()->create(['email' => 'ana@kemuel.test']));

        $this->put(route('central.tenants.update', $tenant), ['name' => 'X', 'nit' => '900000000-1', 'subdomain' => 'kemuel-sas'])
            ->assertSessionHasNoErrors();

        $tenant->refresh();
        expect($tenant->database()->getName())->toBe('tenant_kemuel_sas')
            ->and(DB::table('information_schema.schemata')->where('schema_name', 'tenant_kemuel')->exists())->toBeFalse()
            ->and($tenant->run(fn () => User::query()->where('email', 'ana@kemuel.test')->exists()))->toBeTrue();
    });

    test('moves the Herd site when the subdomain changes', function () {
        Process::fake();
        config(['tenancy.herd.auto_link' => true]);
        $tenant = Tenant::factory()->create(['nit' => '900000000-1']);
        $tenant->domains()->create(['domain' => 'kemuel']);

        $this->put(route('central.tenants.update', $tenant), ['name' => 'X', 'nit' => '900000000-1', 'subdomain' => 'kemuel-sas']);

        Process::assertRan(fn ($process) => $process->command === ['herd', 'unlink', 'kemuel']);
        Process::assertRan(fn ($process) => $process->command === ['herd', 'link', 'kemuel-sas']);
    });

    test('does not call Herd when the subdomain does not change', function () {
        Process::fake();
        config(['tenancy.herd.auto_link' => true]);
        $tenant = Tenant::factory()->create(['nit' => '900000000-1']);
        $tenant->domains()->create(['domain' => 'kemuel']);

        $this->put(route('central.tenants.update', $tenant), ['name' => 'Otro nombre', 'nit' => '900000000-1', 'subdomain' => 'kemuel']);

        Process::assertNothingRan();
    });
});

describe('destroy', function () {
    test('deletes the tenant with its schema, subdomain and migration runs', function () {
        $tenant = Tenant::factory()->create(['name' => 'Kemuel']);
        $tenant->domains()->create(['domain' => 'kemuel']);
        $schema = $tenant->database()->getName();
        MigrationRun::factory()->create(['tenant_id' => $tenant->id]);

        $this->delete(route('central.tenants.destroy', $tenant), ['confirmation' => 'kemuel'])
            ->assertRedirect(route('central.tenants.index'))
            ->assertSessionHas('success', 'Empresa Kemuel eliminada con todos sus datos.');

        expect(Tenant::query()->whereKey($tenant->id)->exists())->toBeFalse()
            ->and(Domain::query()->where('domain', 'kemuel')->exists())->toBeFalse()
            ->and(MigrationRun::query()->where('tenant_id', $tenant->id)->exists())->toBeFalse()
            ->and(DB::table('information_schema.schemata')->where('schema_name', $schema)->exists())->toBeFalse();
    });

    test('requires typing the subdomain to confirm', function (?string $confirmation, string $message) {
        $tenant = Tenant::factory()->create();
        $tenant->domains()->create(['domain' => 'kemuel']);

        $this->delete(route('central.tenants.destroy', $tenant), ['confirmation' => $confirmation])
            ->assertSessionHasErrors(['confirmation' => $message]);

        expect(Tenant::query()->whereKey($tenant->id)->exists())->toBeTrue()
            ->and(DB::table('information_schema.schemata')->where('schema_name', $tenant->database()->getName())->exists())->toBeTrue();
    })->with([
        'missing' => [null, 'Escribe el subdominio de la empresa para confirmar.'],
        'wrong' => ['globex', 'El subdominio no coincide.'],
    ]);

    test('removes the local Herd site of the deleted tenant', function () {
        Process::fake();
        config(['tenancy.herd.auto_link' => true]);
        $tenant = Tenant::factory()->create();
        $tenant->domains()->create(['domain' => 'kemuel']);

        $this->delete(route('central.tenants.destroy', $tenant), ['confirmation' => 'kemuel']);

        Process::assertRan(fn ($process) => $process->command === ['herd', 'unlink', 'kemuel']);
    });
});

test('tenant management requires a central administrator session', function () {
    auth('central')->logout();
    $tenant = Tenant::factory()->create();

    $this->get(route('central.tenants.show', $tenant))->assertRedirect(route('central.login'));
    $this->post(route('central.tenants.store'), validTenantPayload())->assertRedirect(route('central.login'));

    expect(Tenant::count())->toBe(1);
});
