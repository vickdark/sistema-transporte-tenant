<?php

use App\Actions\Tenants\SyncTenantAccessControl;
use App\Enums\BaseRole;
use App\Enums\Permission as PermissionCatalog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

test('every new tenant gets the permission catalog and the base roles', function () {
    expect(Permission::query()->pluck('name')->sort()->values()->all())->toBe(collect(PermissionCatalog::values())->sort()->values()->all())
        ->and(Role::query()->pluck('base_key')->sort()->values()->all())->toBe(collect(BaseRole::cases())->map->value->sort()->values()->all())
        ->and(Role::administrator()->permissions)->toHaveCount(count(PermissionCatalog::cases()))
        ->and(Role::query()->where('base_key', BaseRole::Driver->value)->sole()->permissions)->toHaveCount(0);
});

test('the sync is idempotent, keeps renamed base roles and removes permissions out of the catalog', function () {
    Role::query()->where('base_key', BaseRole::Driver->value)->update(['name' => 'Chofer']);
    Permission::create(['name' => 'legacy.removed', 'guard_name' => 'web']);

    app(SyncTenantAccessControl::class)->handle();

    expect(Role::query()->where('base_key', BaseRole::Driver->value)->sole()->name)->toBe('Chofer')
        ->and(Role::query()->where('name', 'Conductor')->exists())->toBeFalse()
        ->and(Permission::query()->where('name', 'legacy.removed')->exists())->toBeFalse()
        ->and(Role::query()->count())->toBe(count(BaseRole::cases()));
});

test('the database allows only one role per user', function () {
    $user = User::factory()->withRole(BaseRole::Driver)->create();

    DB::table('model_has_roles')->insert([
        'role_id' => Role::query()->where('base_key', BaseRole::Coordinator->value)->value('id'),
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->id,
    ]);
})->throws(UniqueConstraintViolationException::class);

test('the last administrator can not delete their own account', function () {
    $admin = User::factory()->withRole(BaseRole::Administrator)->create();

    $this->actingAs($admin)
        ->delete('/settings/profile', ['password' => 'password'])
        ->assertSessionHasErrors(['password' => 'La empresa debe conservar al menos un usuario con el rol Administrador.']);

    expect(User::query()->whereKey($admin->id)->exists())->toBeTrue();
});

test('roles and permissions are isolated between tenants, including the permission cache', function () {
    $acme = tenant();
    $acmeUser = User::factory()->withRole(BaseRole::Administrator)->create();
    expect($acmeUser->can('users.view'))->toBeTrue();

    // En globex el usuario con el mismo id no tiene rol: no debe heredar nada de acme.
    $this->initializeTenant('globex');
    $globexUser = User::factory()->create();
    expect($globexUser->id)->toBe($acmeUser->id)
        ->and($globexUser->can('users.view'))->toBeFalse();

    $this->actingAs($globexUser)->get($this->tenantUrl('globex', '/users'))->assertForbidden();

    tenancy()->initialize($acme);
    expect(User::find($acmeUser->id)->can('users.view'))->toBeTrue();
});

test('the frontend receives the permissions and role of the user', function () {
    $this->actingAs(User::factory()->withRole(BaseRole::Administrator)->create());

    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('auth.role', 'Administrador')
        ->where('auth.permissions', fn ($permissions) => collect($permissions)->sort()->values()->all() === collect(PermissionCatalog::values())->sort()->values()->all())
    );
});

test('the sync command gives the administrator role to an initial user without role', function () {
    $tenant = tenant();
    $user = User::factory()->create();
    $tenant->update(['initial_user_id' => $user->id]);
    tenancy()->end();

    $this->artisan('tenants:sync-access', ['--tenants' => [$tenant->id]])->assertSuccessful();

    expect($tenant->run(fn () => $user->refresh()->hasRole(Role::administrator())))->toBeTrue();
});
