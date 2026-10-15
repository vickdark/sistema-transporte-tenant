<?php

use App\Enums\TenantStatus;
use App\Models\CentralUser;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('suspending a tenant blocks its users without deleting data', function () {
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => 'acme']);
    $user = $tenant->run(fn () => User::factory()->create());

    $this->actingAs(CentralUser::factory()->create(), 'central')
        ->post(route('central.tenants.suspend', $tenant))
        ->assertSessionHas('success');

    expect($tenant->refresh()->status)->toBe(TenantStatus::Suspended)
        ->and($tenant->suspended_at)->not->toBeNull()
        ->and($tenant->run(fn () => User::query()->whereKey($user->id)->exists()))->toBeTrue();

    $this->actingAs($user)
        ->get($this->tenantUrl('acme', '/dashboard'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('TenantSuspended'));

    $this->get($this->tenantUrl('acme', '/login'))->assertForbidden();
});

test('reactivating a tenant restores access', function () {
    $tenant = Tenant::factory()->suspended()->create();
    $tenant->domains()->create(['domain' => 'acme']);

    $this->actingAs(CentralUser::factory()->create(), 'central')
        ->post(route('central.tenants.reactivate', $tenant));

    expect($tenant->refresh()->status)->toBe(TenantStatus::Active)
        ->and($tenant->suspended_at)->toBeNull();

    auth()->shouldUse('web');
    $this->get($this->tenantUrl('acme', '/login'))->assertOk();
});

test('tenant users can not suspend tenants', function () {
    $tenant = Tenant::factory()->create();
    $user = $tenant->run(fn () => User::factory()->create());

    $this->actingAs($user)
        ->post(route('central.tenants.suspend', $tenant))
        ->assertRedirect(route('central.login'));

    expect($tenant->refresh()->status)->toBe(TenantStatus::Active);
});
