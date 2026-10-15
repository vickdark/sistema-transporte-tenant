<?php

use App\Models\CentralUser;
use App\Models\Tenant;
use App\Models\User;

test('the central login screen can be rendered', function () {
    $this->get(route('central.login'))->assertOk();
});

test('central administrators can authenticate', function () {
    $admin = CentralUser::factory()->create();

    $response = $this->post(route('central.login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($admin, 'central');
    $response->assertRedirect(route('central.tenants.index', absolute: false));
});

test('central administrators can not authenticate with an invalid password', function () {
    $admin = CentralUser::factory()->create();

    $this->post(route('central.login.store'), [
        'email' => $admin->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest('central');
});

test('tenant users can not authenticate on the central panel', function () {
    $user = Tenant::factory()->create()->run(fn () => User::factory()->create());

    $response = $this->post(route('central.login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest('central');
    $response->assertSessionHasErrors(['email' => 'Estas credenciales no coinciden con nuestros registros.']);
});

test('guests are redirected to the central login', function () {
    $this->get(route('central.tenants.index'))->assertRedirect(route('central.login'));
});

test('central administrators can logout', function () {
    $admin = CentralUser::factory()->create();

    $response = $this->actingAs($admin, 'central')->post(route('central.logout'));

    $this->assertGuest('central');
    $response->assertRedirect(route('central.login'));
});

test('the central user is shared with the frontend', function () {
    $admin = CentralUser::factory()->create();

    $this->actingAs($admin, 'central')
        ->get(route('central.tenants.index'))
        ->assertInertia(fn ($page) => $page->where('auth.user.email', $admin->email));
});
