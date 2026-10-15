<?php

use App\Models\User;
use App\Notifications\TenantUserInvitation;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('the invitation screen can be rendered', function () {
    $user = User::factory()->unverified()->create();
    $url = (new TenantUserInvitation(tenant()))->invitationUrl($user);

    $this->get($url)->assertInertia(fn (Assert $page) => $page
        ->component('auth/AcceptInvitation')
        ->where('email', $user->email)
    );
});

test('invited users set their password and are logged in', function () {
    $user = User::factory()->unverified()->create();
    $url = (new TenantUserInvitation(tenant()))->invitationUrl($user);

    $response = $this->post($url, [
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($user);
    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->invitation_accepted_at)->not->toBeNull();
});

test('an invitation link can not be used twice', function () {
    $user = User::factory()->create();
    $url = (new TenantUserInvitation(tenant()))->invitationUrl($user);

    $this->post($url, ['password' => 'new-password', 'password_confirmation' => 'new-password']);
    auth()->logout();

    $this->get($url)->assertForbidden();
    $this->post($url, ['password' => 'other-password', 'password_confirmation' => 'other-password'])->assertForbidden();

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('a tampered or expired invitation link is rejected', function () {
    $user = User::factory()->create();
    $url = (new TenantUserInvitation(tenant()))->invitationUrl($user);

    $this->get(preg_replace('/hash=\w+/', 'hash='.str_repeat('0', 64), $url))->assertForbidden();

    $this->travel(TenantUserInvitation::EXPIRATION_DAYS + 1)->days();
    $this->get($url)->assertForbidden();
});

test('an invitation does not work on another tenant domain', function () {
    $user = User::factory()->create();
    $url = (new TenantUserInvitation(tenant()))->invitationUrl($user);

    $this->initializeTenant('globex');

    $this->get(str_replace('//acme.', '//globex.', $url))->assertNotFound();
});
