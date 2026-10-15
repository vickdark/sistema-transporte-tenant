<?php

namespace App\Console\Commands;

use App\Actions\Tenants\SyncTenantAccessControl;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Sincroniza el catálogo de permisos y los roles base en el schema de cada empresa. Se ejecuta en
 * cada despliegue (después de tenants:migrate). A las empresas cuyo usuario inicial no tiene rol
 * (creadas antes de la Fase 1) se le asigna el rol Administrador.
 */
class SyncTenantsAccess extends Command
{
    /**
     * @var string
     */
    protected $signature = 'tenants:sync-access {--tenants=* : IDs de las empresas (por defecto, todas)}';

    /**
     * @var string
     */
    protected $description = 'Sincroniza permisos y roles base en el schema de cada empresa';

    public function handle(SyncTenantAccessControl $sync): int
    {
        $tenants = Tenant::query()
            ->when($this->option('tenants'), fn ($query, array $ids) => $query->whereKey($ids))
            ->get();

        foreach ($tenants as $tenant) {
            $tenant->run(function () use ($sync, $tenant): void {
                $sync->handle();

                $initialUser = $tenant->initialUser();

                if ($initialUser !== null && $initialUser->roles()->doesntExist()) {
                    $initialUser->syncRoles([Role::administrator()]);
                    $this->line("  {$tenant->name}: rol Administrador asignado a {$initialUser->username}.");
                }
            });

            $this->info("{$tenant->name}: permisos y roles sincronizados.");
        }

        return self::SUCCESS;
    }
}
