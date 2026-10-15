<?php

namespace Tests;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\URL;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create a tenant with the given domain, initialize tenancy for it and
     * send the following requests to that domain.
     */
    protected function initializeTenant(string $domain = 'acme.test'): Tenant
    {
        $tenant = Tenant::factory()->create();
        $tenant->domains()->create(['domain' => $domain]);

        tenancy()->initialize($tenant);
        URL::forceRootUrl("http://{$domain}");

        return $tenant;
    }
}
