<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('users can not authenticate on another tenant domain', function () {
    $this->initializeTenant('acme.test');
    $user = User::factory()->create();

    $this->initializeTenant('globex.test');

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
});

test('the same email can register in different tenants', function () {
    $this->initializeTenant('acme.test');
    User::factory()->create(['email' => 'driver@example.com']);

    $globex = $this->initializeTenant('globex.test');

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'driver@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertAuthenticated();
    expect(auth()->user()->tenant_id)->toBe($globex->id);
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
    $response->assertSessionHasErrors(['email' => 'The email has already been taken.']);
});

test('profile email can be changed to an email used in another tenant', function () {
    $this->initializeTenant('acme.test');
    User::factory()->create(['email' => 'driver@example.com']);

    $this->initializeTenant('globex.test');
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

    $response->assertSessionHasErrors(['email' => 'The email has already been taken.']);
    expect($user->refresh()->email)->not->toBe('driver@example.com');
});

test('tenant routes are not available on the central domain', function (string $path) {
    $centralDomain = config('tenancy.central_domains')[0];

    $this->get("http://{$centralDomain}{$path}")->assertNotFound();
})->with([
    'login' => '/login',
    'register' => '/register',
    'dashboard' => '/dashboard',
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

    $this->get("http://{$centralDomain}/")->assertInertia(fn (Assert $page) => $page
        ->component('Welcome')
        ->where('tenant', null)
    );
});
