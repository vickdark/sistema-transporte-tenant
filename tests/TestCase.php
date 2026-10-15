<?php

namespace Tests;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

abstract class TestCase extends BaseTestCase
{
    private static bool $leftoverSchemasDropped = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Un proceso interrumpido puede dejar schemas de empresas de una corrida anterior.
        if (! self::$leftoverSchemasDropped) {
            $this->dropTenantSchemas();
            self::$leftoverSchemasDropped = true;
        }
    }

    /**
     * Las tablas centrales las limpia DatabaseTruncation (con la conexión central, por eso se
     * termina la tenancy antes); los schemas de las empresas se borran aquí.
     */
    protected function tearDown(): void
    {
        if ($this->app !== null) {
            tenancy()->end();
            $this->dropTenantSchemas();
        }

        parent::tearDown();
    }

    /**
     * Create a tenant (with its migrated schema) on the given subdomain, initialize tenancy
     * for it and send the following requests to its host.
     */
    protected function initializeTenant(string $subdomain = 'acme'): Tenant
    {
        $tenant = Tenant::factory()->create();
        $tenant->domains()->create(['domain' => $subdomain]);

        tenancy()->initialize($tenant);
        URL::forceRootUrl($this->tenantUrl($subdomain));

        return $tenant;
    }

    /**
     * Absolute URL on a tenant's host ({subdomain}.{tenant base domain}).
     */
    protected function tenantUrl(string $subdomain, string $path = ''): string
    {
        return 'http://'.$subdomain.'.'.config('tenancy.tenant_base_domain').$path;
    }

    private function dropTenantSchemas(): void
    {
        $central = DB::connection(config('tenancy.database.central_connection'));
        $prefix = config('tenancy.database.prefix');

        $schemas = $central->table('information_schema.schemata')
            ->where('schema_name', 'like', str_replace('_', '\_', $prefix).'%')
            ->pluck('schema_name');

        foreach ($schemas as $schema) {
            $central->statement("DROP SCHEMA \"{$schema}\" CASCADE");
        }

        DB::purge('tenant');
    }
}
