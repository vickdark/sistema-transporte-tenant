<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate with their username or their email', function (string $field) {
    $user = User::factory()->create(['username' => 'juan.perez', 'email' => 'juan@acme.test']);

    $response = $this->post('/login', [
        'login' => $field === 'username' ? 'juan.perez' : 'juan@acme.test',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
})->with(['username', 'email']);

test('the login is case insensitive and ignores surrounding spaces', function () {
    $user = User::factory()->create(['username' => 'juan.perez']);

    $this->post('/login', ['login' => '  Juan.Perez ', 'password' => 'password']);

    $this->assertAuthenticatedAs($user);
});

test('users without email can authenticate with their username', function () {
    $user = User::factory()->create(['username' => 'conductor1', 'email' => null]);

    $this->post('/login', ['login' => 'conductor1', 'password' => 'password']);

    $this->assertAuthenticatedAs($user);
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'login' => $user->username,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors(['login' => 'Estas credenciales no coinciden con nuestros registros.']);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

test('the public registration is not available', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', ['name' => 'X', 'email' => 'x@acme.test'])->assertNotFound();

    expect(User::count())->toBe(0);
});
