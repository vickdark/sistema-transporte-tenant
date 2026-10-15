<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('users can not authenticate on another tenant domain', function () {
    $this->initializeTenant('acme');
    $user = User::factory()->create();

    $this->initializeTenant('globex');

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors(['email' => 'Estas credenciales no coinciden con nuestros registros.']);
});

test('the same email can register in different tenants', function () {
    $this->initializeTenant('acme');
    User::factory()->create(['email' => 'driver@example.com']);

    $acme = tenant();
    $this->initializeTenant('globex');

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'driver@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertAuthenticated();
    expect(User::query()->where('email', 'driver@example.com')->count())->toBe(1)
        ->and($acme->run(fn () => User::query()->where('email', 'driver@example.com')->count()))->toBe(1);
});

test('registration requires an email that is unique within the tenant', function () {
    $this->initializeTenant();
    User::factory()->create(['email' => 'driver@example.com']);

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'driver@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors(['email' => 'El valor del campo correo electrónico ya está en uso.']);
});

test('profile email can be changed to an email used in another tenant', function () {
    $this->initializeTenant('acme');
    User::factory()->create(['email' => 'driver@example.com']);

    $this->initializeTenant('globex');
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/settings/profile', [
            'name' => $user->name,
            'email' => 'driver@example.com',
        ]);

    $response->assertSessionHasNoErrors();
    expect($user->refresh()->email)->toBe('driver@example.com');
});

test('profile email must be unique within the tenant', function () {
    $this->initializeTenant();
    User::factory()->create(['email' => 'driver@example.com']);
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/settings/profile', [
            'name' => $user->name,
            'email' => 'driver@example.com',
        ]);

    $response->assertSessionHasErrors(['email' => 'El valor del campo correo electrónico ya está en uso.']);
    expect($user->refresh()->email)->not->toBe('driver@example.com');
});

test('tenant routes are not available on the central domain', function (string $path) {
    $centralDomain = config('tenancy.central_domains')[0];

    $this->get("http://{$centralDomain}{$path}")->assertNotFound();
})->with([
    'register' => '/register',
    'dashboard' => '/dashboard',
    'settings' => '/settings/profile',
]);

test('the current tenant is shared with the frontend', function () {
    $tenant = $this->initializeTenant();

    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('tenant.id', $tenant->id)
        ->where('tenant.name', $tenant->name)
    );
});

test('no tenant is shared on the central domain', function () {
    $centralDomain = config('tenancy.central_domains')[0];

    $this->get("http://{$centralDomain}/login")->assertInertia(fn (Assert $page) => $page
        ->component('central/auth/Login')
        ->where('tenant', null)
    );
});

test('a subdomain that does not belong to any tenant returns 404', function () {
    $this->initializeTenant('acme');

    $this->get($this->tenantUrl('unknown', '/login'))->assertNotFound();
});
