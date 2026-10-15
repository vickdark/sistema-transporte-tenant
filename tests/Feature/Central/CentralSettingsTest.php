<?php

use App\Models\CentralUser;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('the central settings pages can be rendered', function (string $routeName, string $component) {
    $this->actingAs(CentralUser::factory()->create(), 'central')
        ->get(route($routeName))
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    'password' => ['central.settings.password.edit', 'central/settings/Password'],
    'appearance' => ['central.settings.appearance', 'central/settings/Appearance'],
]);

test('central administrators can update their password', function () {
    $admin = CentralUser::factory()->create();

    $this->actingAs($admin, 'central')
        ->from(route('central.settings.password.edit'))
        ->put(route('central.settings.password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('central.settings.password.edit'));

    expect(Hash::check('new-password', $admin->refresh()->password))->toBeTrue();
});

test('the current password must be correct to update it', function () {
    $admin = CentralUser::factory()->create();

    $this->actingAs($admin, 'central')
        ->put(route('central.settings.password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('password', $admin->refresh()->password))->toBeTrue();
});

test('guests can not reach the central settings', function () {
    $this->get(route('central.settings.password.edit'))->assertRedirect(route('central.login'));
});
