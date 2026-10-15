<?php

use App\Enums\BaseRole;
use App\Models\Role;
use App\Models\User;
use App\Notifications\TenantUserInvitation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ExportedSpreadsheet;

beforeEach(function () {
    $this->admin = User::factory()->withRole(BaseRole::Administrator)->create(['username' => 'admin']);
    $this->actingAs($this->admin);
});

function roleId(BaseRole $baseRole): int
{
    return Role::query()->where('base_key', $baseRole->value)->value('id');
}

/**
 * @return array<string, mixed>
 */
function userPayload(array $overrides = []): array
{
    return [
        'name' => 'Juan Pérez',
        'username' => 'juan.perez',
        'email' => '',
        'role_id' => roleId(BaseRole::Driver),
        'send_invitation' => false,
        'password' => 'clave-segura-123',
        'password_confirmation' => 'clave-segura-123',
        ...$overrides,
    ];
}

describe('index', function () {
    test('lists the users with their role', function () {
        User::factory()->withRole(BaseRole::Driver)->create(['name' => 'Carlos Conductor', 'username' => 'carlos']);

        $this->get(route('users.index', ['search' => 'carlos']))->assertInertia(fn (Assert $page) => $page
            ->component('users/Index')
            ->has('users.data', 1)
            ->where('users.data.0.username', 'carlos')
            ->where('users.data.0.role.name', 'Conductor')
        );
    });

    test('filters the users by role', function () {
        User::factory()->withRole(BaseRole::Driver)->create(['username' => 'carlos']);

        $this->get(route('users.index', ['role' => roleId(BaseRole::Driver)]))->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.username', 'carlos')
        );
    });

    test('sorts and paginates the users in the server', function () {
        User::factory()->withRole(BaseRole::Driver)->create(['username' => 'zoe']);
        User::factory()->withRole(BaseRole::Driver)->create(['username' => 'bruno']);

        $this->get(route('users.index', ['sort' => 'username', 'direction' => 'desc', 'per_page' => 10]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('users.data.0.username', 'zoe')
                ->where('users.per_page', 10)
                ->where('query', fn ($query) => $query['sort'] === 'username' && $query['direction'] === 'desc')
            );
    });

    test('falls back to the default order for a column that cannot be sorted', function () {
        $this->get(route('users.index', ['sort' => 'password', 'per_page' => 7]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('query.sort', 'name')
                ->where('query.per_page', 15)
            );
    });
});

describe('export', function () {
    test('exports to excel the same users the screen shows, in the same order', function () {
        User::factory()->withRole(BaseRole::Driver)->create(['name' => 'Bruno Díaz', 'username' => 'bruno', 'email' => null]);
        User::factory()->withRole(BaseRole::Driver)->create(['name' => 'Ana Ruiz', 'username' => 'ana']);
        User::factory()->withRole(BaseRole::Coordinator)->create(['username' => 'otro']);

        $response = $this->get(route('users.export', ['format' => 'xlsx', 'role' => roleId(BaseRole::Driver), 'sort' => 'username', 'direction' => 'desc']));

        $response->assertOk()->assertDownload();
        expect(ExportedSpreadsheet::rows($response))->toBe([
            ['Nombre', 'Usuario', 'Correo', 'Rol', 'Estado'],
            ['Bruno Díaz', 'bruno', '', 'Conductor', 'Activo'],
            ['Ana Ruiz', 'ana', User::query()->where('username', 'ana')->value('email'), 'Conductor', 'Activo'],
        ]);
    });

    test('exports the users to pdf', function () {
        $response = $this->get(route('users.export', ['format' => 'pdf']));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        expect($response->getContent())->toStartWith('%PDF');
    });

    test('exporting requires permission to view users', function () {
        $this->actingAs(User::factory()->withRole(BaseRole::Driver)->create());

        $this->get(route('users.export', ['format' => 'xlsx']))->assertForbidden();
    });
});

describe('store', function () {
    test('creates a user with a password and a single role', function () {
        $this->post(route('users.store'), userPayload())
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success', 'Usuario juan.perez creado.');

        $user = User::query()->where('username', 'juan.perez')->sole();
        expect($user->email)->toBeNull()
            ->and(Hash::check('clave-segura-123', $user->password))->toBeTrue()
            ->and($user->roles->pluck('name')->all())->toBe(['Conductor']);
    });

    test('invites the user by email instead of setting a password', function () {
        Notification::fake();

        $this->post(route('users.store'), userPayload(['email' => 'juan@acme.test', 'send_invitation' => true, 'password' => '']))
            ->assertSessionHas('success', 'Usuario juan.perez creado. Se envió la invitación a juan@acme.test.');

        $user = User::query()->where('username', 'juan.perez')->sole();
        expect($user->hasPendingInvitation())->toBeTrue();
        Notification::assertSentTo($user, TenantUserInvitation::class);
    });

    test('requires an email to send the invitation', function () {
        $this->post(route('users.store'), userPayload(['send_invitation' => true]))
            ->assertSessionHasErrors(['email' => 'El correo es obligatorio para enviar la invitación.']);
    });

    test('requires a password when no invitation is sent', function () {
        $this->post(route('users.store'), userPayload(['password' => '', 'password_confirmation' => '']))
            ->assertSessionHasErrors(['password' => 'El campo contraseña es obligatorio.']);
    });

    test('rejects a taken username or email and an invalid username', function (array $overrides, string $field, string $message) {
        User::factory()->create(['username' => 'juan.perez', 'email' => 'juan@acme.test']);

        $this->post(route('users.store'), userPayload($overrides))->assertSessionHasErrors([$field => $message]);
    })->with([
        'taken username' => [[], 'username', 'El valor del campo usuario ya está en uso.'],
        'taken email' => [['username' => 'otro', 'email' => 'juan@acme.test'], 'email', 'El valor del campo correo ya está en uso.'],
        'username with @' => [['username' => 'juan@perez'], 'username', 'El usuario debe tener entre 3 y 50 caracteres: minúsculas, números, punto, guion o guion bajo.'],
    ]);

    test('accepts a username and email already used in another tenant', function () {
        $acme = tenant();
        $this->initializeTenant('globex')->run(fn () => User::factory()->create(['username' => 'juan.perez', 'email' => 'juan@acme.test']));
        tenancy()->initialize($acme);
        $this->actingAs($this->admin);

        $this->post($this->tenantUrl('acme', '/users'), userPayload(['email' => 'juan@acme.test']))->assertSessionHasNoErrors();

        expect(User::query()->where('username', 'juan.perez')->exists())->toBeTrue();
    });
});

describe('update', function () {
    test('changes the data, the role and optionally the password', function () {
        $user = User::factory()->withRole(BaseRole::Driver)->create(['username' => 'juan.perez']);

        $this->put(route('users.update', $user), userPayload([
            'name' => 'Juan P.',
            'role_id' => roleId(BaseRole::Coordinator),
            'password' => 'otra-clave-segura',
            'password_confirmation' => 'otra-clave-segura',
        ]))->assertRedirect(route('users.index'));

        $user->refresh();
        expect($user->name)->toBe('Juan P.')
            ->and($user->roles->pluck('name')->all())->toBe(['Coordinador'])
            ->and(Hash::check('otra-clave-segura', $user->password))->toBeTrue();
    });

    test('keeps the password when it is left empty', function () {
        $user = User::factory()->withRole(BaseRole::Driver)->create(['username' => 'juan.perez']);
        $currentHash = $user->password;

        $this->put(route('users.update', $user), userPayload(['password' => '', 'password_confirmation' => '']))
            ->assertSessionHasNoErrors();

        expect($user->refresh()->password)->toBe($currentHash);
    });

    test('does not remove the administrator role from the last administrator', function () {
        $this->put(route('users.update', $this->admin), userPayload([
            'username' => 'admin',
            'role_id' => roleId(BaseRole::Driver),
            'password' => '',
            'password_confirmation' => '',
        ]))->assertSessionHasErrors(['role' => 'La empresa debe conservar al menos un usuario con el rol Administrador.']);

        expect($this->admin->refresh()->hasRole(Role::administrator()))->toBeTrue();
    });
});

describe('destroy', function () {
    test('deletes another user', function () {
        $user = User::factory()->withRole(BaseRole::Driver)->create();

        $this->delete(route('users.destroy', $user))->assertSessionHas('success');

        expect(User::query()->whereKey($user->id)->exists())->toBeFalse();
    });

    test('forbids deleting yourself', function () {
        $this->delete(route('users.destroy', $this->admin))->assertForbidden();

        expect(User::query()->whereKey($this->admin->id)->exists())->toBeTrue();
    });
});

describe('authorization', function () {
    test('users need the matching permission for each action', function (string $method, Closure $url, string $permission) {
        $role = Role::create(['name' => 'Consulta', 'guard_name' => 'web']);
        $limited = User::factory()->create();
        $limited->syncRoles([$role]);
        $target = User::factory()->withRole(BaseRole::Driver)->create();
        $this->actingAs($limited);

        $this->call($method, $url($target), userPayload())->assertForbidden();

        $role->givePermissionTo($permission);
        $this->call($method, $url($target), userPayload())->assertSuccessful();
    })->with([
        'list' => ['GET', fn () => route('users.index'), 'users.view'],
        'create form' => ['GET', fn () => route('users.create'), 'users.create'],
        'edit form' => ['GET', fn (User $target) => route('users.edit', $target), 'users.update'],
    ]);

    test('a user without users.create can not create users', function () {
        $limited = User::factory()->withRole(BaseRole::Driver)->create();
        $this->actingAs($limited);

        $this->post(route('users.store'), userPayload())->assertForbidden();

        expect(User::query()->where('username', 'juan.perez')->exists())->toBeFalse();
    });
});
