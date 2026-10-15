<?php

use App\Enums\BaseRole;
use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->withRole(BaseRole::Administrator)->create());
});

test('lists the base roles with the administrator first', function () {
    $this->get(route('roles.index'))->assertInertia(fn (Assert $page) => $page
        ->component('roles/Index')
        ->has('roles.data', count(BaseRole::cases()))
        ->where('roles.data.0.name', 'Administrador')
        ->where('roles.data.0.is_protected', true)
        ->where('roles.data.0.permissions_count', count(Permission::cases()))
        ->where('roles.data.0.can.update', false)
    );
});

test('shows the permission matrix grouped by module', function () {
    $this->get(route('roles.create'))->assertInertia(fn (Assert $page) => $page
        ->where('permissionGroups.0.module', 'Usuarios')
        ->where('permissionGroups.0.permissions.0.name', 'users.view')
    );
});

test('creates a role with its permissions', function () {
    $this->post(route('roles.store'), [
        'name' => 'Supervisor',
        'description' => 'Revisa usuarios',
        'permissions' => ['users.view', 'users.update'],
    ])->assertRedirect(route('roles.index'));

    $role = Role::findByName('Supervisor');
    expect($role->permissions->pluck('name')->sort()->values()->all())->toBe(['users.update', 'users.view']);
});

test('rejects permissions outside the catalog and duplicated names', function () {
    $this->post(route('roles.store'), [
        'name' => 'Conductor',
        'permissions' => ['users.view', 'orders.fly'],
    ])->assertSessionHasErrors(['name', 'permissions.1']);
});

test('updates the permissions of a base role', function () {
    $driver = Role::query()->where('base_key', BaseRole::Driver->value)->sole();

    $this->put(route('roles.update', $driver), ['name' => 'Conductor', 'permissions' => ['users.view']])
        ->assertSessionHasNoErrors();

    expect($driver->refresh()->permissions->pluck('name')->all())->toBe(['users.view']);
});

test('the administrator role can not be edited or deleted', function () {
    $administrator = Role::administrator();

    $this->put(route('roles.update', $administrator), ['name' => 'Jefe', 'permissions' => []])->assertForbidden();
    $this->delete(route('roles.destroy', $administrator))->assertForbidden();

    expect($administrator->refresh()->name)->toBe('Administrador')
        ->and($administrator->permissions)->toHaveCount(count(Permission::cases()));
});

test('deletes a role without users but not one in use', function () {
    $unused = Role::create(['name' => 'Temporal', 'guard_name' => 'web']);
    $driver = Role::query()->where('base_key', BaseRole::Driver->value)->sole();
    User::factory()->withRole(BaseRole::Driver)->create();

    $this->delete(route('roles.destroy', $unused))->assertSessionHas('success');
    $this->delete(route('roles.destroy', $driver))
        ->assertSessionHasErrors(['role' => 'No se puede eliminar Conductor: lo tienen 1 usuario(s). Asígnales otro rol primero.']);

    expect(Role::query()->whereKey($unused->id)->exists())->toBeFalse()
        ->and(Role::query()->whereKey($driver->id)->exists())->toBeTrue();
});

test('managing roles requires roles.manage', function () {
    $this->actingAs(User::factory()->withRole(BaseRole::Driver)->create());

    $this->get(route('roles.index'))->assertForbidden();
    $this->post(route('roles.store'), ['name' => 'X', 'permissions' => []])->assertForbidden();

    expect(Role::query()->where('name', 'X')->exists())->toBeFalse();
});
