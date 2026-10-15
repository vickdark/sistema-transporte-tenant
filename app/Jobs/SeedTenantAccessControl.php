<?php

namespace App\Jobs;

use App\Actions\Tenants\SyncTenantAccessControl;
use App\Models\Tenant;

/**
 * Paso del alta de una empresa (después de migrar su schema): siembra permisos y roles base.
 */
class SeedTenantAccessControl
{
    public function __construct(public Tenant $tenant) {}

    public function handle(SyncTenantAccessControl $sync): void
    {
        $this->tenant->run(fn () => $sync->handle());
    }
}
