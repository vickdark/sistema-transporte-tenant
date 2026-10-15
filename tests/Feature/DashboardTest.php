<?php

use App\Enums\BaseRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->withRole(BaseRole::Driver)->create();
    $this->actingAs($user);

    $response = $this->get('/dashboard');
    $response->assertStatus(200);
});

test('users without a role see the no access page', function (string $path) {
    $this->actingAs(User::factory()->create());

    $this->get($path)
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('NoAccess'));
})->with(['/dashboard', '/users', '/roles']);

test('users without a role can still manage their profile', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/settings/profile')->assertOk();
});
