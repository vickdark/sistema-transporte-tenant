<?php

namespace App\Actions\Tenants;

use App\Models\MigrationRun;
use App\Models\Tenant;

/**
 * Elimina una empresa de forma definitiva: su schema completo (todos sus datos, vía el evento
 * TenantDeleted), su subdominio, sus corridas de migración y, en local, su sitio de Herd.
 */
class DeleteTenant
{
    public function __construct(private RegisterLocalTenantHost $localHost) {}

    public function handle(Tenant $tenant): void
    {
        $subdomain = $tenant->subdomain?->domain;

        MigrationRun::query()->where('tenant_id', $tenant->getTenantKey())->delete();

        $tenant->delete();

        if ($subdomain !== null) {
            $this->localHost->remove($subdomain);
        }
    }
}
