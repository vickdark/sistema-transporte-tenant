<?php

use App\Models\CentralUser;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantUserInvitation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(CentralUser::factory()->create(), 'central');

    $this->tenant = Tenant::factory()->create();
    $this->tenant->domains()->create(['domain' => 'kemuel']);
    $this->admin = $this->tenant->run(fn () => User::factory()->unverified()->create([
        'name' => 'Ana Gerente',
        'username' => 'ana',
        'email' => 'ana@kemuel.test',
        'invited_at' => now(),
    ]));
    $this->tenant->update(['initial_user_id' => $this->admin->id]);
});

/**
 * @return array<string, mixed>
 */
function initialUserPayload(array $overrides = []): array
{
    return [
        'name' => 'Ana Gerente',
        'username' => 'ana',
        'email' => 'ana@kemuel.test',
        'send_invitation' => false,
        ...$overrides,
    ];
}

test('shows the initial user with its invitation status on the tenant page', function () {
    $this->get(route('central.tenants.show', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->where('initialUser.name', 'Ana Gerente')
        ->where('initialUser.email', 'ana@kemuel.test')
        ->where('initialUser.invitation_pending', true)
    );
});

test('updates the name and email of the initial user in the tenant schema', function () {
    Notification::fake();

    $this->put(route('central.tenants.initial-user.update', $this->tenant), initialUserPayload([
        'name' => 'Ana María Gerente',
        'email' => 'gerencia@kemuel.test',
    ]))->assertSessionHas('success', 'Usuario inicial actualizado.');

    $admin = $this->tenant->run(fn () => $this->admin->refresh());
    expect($admin->name)->toBe('Ana María Gerente')
        ->and($admin->email)->toBe('gerencia@kemuel.test');
    Notification::assertNothingSent();
});

test('rejects an email used by another user of the same tenant', function () {
    $this->tenant->run(fn () => User::factory()->create(['email' => 'otro@kemuel.test']));

    $this->put(route('central.tenants.initial-user.update', $this->tenant), initialUserPayload(['email' => 'otro@kemuel.test']))
        ->assertSessionHasErrors(['email' => 'Otro usuario de esta empresa ya usa este correo.']);

    expect($this->tenant->run(fn () => $this->admin->refresh()->email))->toBe('ana@kemuel.test');
});

test('accepts an email that another tenant already uses', function () {
    $globex = Tenant::factory()->create();
    $globex->run(fn () => User::factory()->create(['email' => 'shared@example.com']));

    $this->put(route('central.tenants.initial-user.update', $this->tenant), initialUserPayload(['email' => 'shared@example.com']))
        ->assertSessionHasNoErrors();

    expect($this->tenant->run(fn () => $this->admin->refresh()->email))->toBe('shared@example.com');
});

test('sends a new invitation to the changed email when requested, even after it was accepted', function () {
    Notification::fake();
    $this->tenant->run(fn () => $this->admin->forceFill(['invitation_accepted_at' => now()])->save());

    $this->put(route('central.tenants.initial-user.update', $this->tenant), initialUserPayload([
        'email' => 'nuevo@kemuel.test',
        'send_invitation' => true,
    ]))->assertSessionHas('success', 'Usuario inicial actualizado. Se envió la invitación a nuevo@kemuel.test.');

    $admin = $this->tenant->run(fn () => $this->admin->refresh());
    expect($admin->email)->toBe('nuevo@kemuel.test')
        ->and($admin->hasPendingInvitation())->toBeTrue();
    Notification::assertSentTo($admin, TenantUserInvitation::class, fn ($notification, array $channels, User $notifiable) => $notifiable->email === 'nuevo@kemuel.test');
});

test('sets a new password from the panel and clears the pending invitation', function () {
    $this->put(route('central.tenants.initial-user.update', $this->tenant), initialUserPayload([
        'password' => 'nueva-clave-segura',
        'password_confirmation' => 'nueva-clave-segura',
    ]))->assertSessionHas('success', 'Usuario inicial actualizado con la nueva contraseña.');

    $admin = $this->tenant->run(fn () => $this->admin->refresh());
    expect(Hash::check('nueva-clave-segura', $admin->password))->toBeTrue()
        ->and($admin->hasPendingInvitation())->toBeFalse()
        ->and($admin->email_verified_at)->not->toBeNull();
});

test('keeps the current password when the password is left empty', function () {
    $currentHash = $this->admin->password;

    $this->put(route('central.tenants.initial-user.update', $this->tenant), initialUserPayload(['password' => '']))
        ->assertSessionHasNoErrors();

    expect($this->tenant->run(fn () => $this->admin->refresh()->password))->toBe($currentHash);
});

test('requires the new password to be confirmed', function () {
    $this->put(route('central.tenants.initial-user.update', $this->tenant), initialUserPayload([
        'password' => 'nueva-clave-segura',
        'password_confirmation' => 'otra',
    ]))->assertSessionHasErrors(['password' => 'La confirmación del campo contraseña no coincide.']);
});

test('ignores the password when an invitation is sent instead', function () {
    Notification::fake();
    $currentHash = $this->admin->password;

    $this->put(route('central.tenants.initial-user.update', $this->tenant), initialUserPayload([
        'send_invitation' => true,
        'password' => 'nueva-clave-segura',
        'password_confirmation' => 'nueva-clave-segura',
    ]))->assertSessionHasNoErrors();

    expect($this->tenant->run(fn () => $this->admin->refresh()->password))->toBe($currentHash);
});

test('managing the initial user requires a central administrator session', function () {
    auth('central')->logout();

    $this->put(route('central.tenants.initial-user.update', $this->tenant), initialUserPayload(['name' => 'X']))
        ->assertRedirect(route('central.login'));

    expect($this->tenant->run(fn () => $this->admin->refresh()->name))->toBe('Ana Gerente');
});
