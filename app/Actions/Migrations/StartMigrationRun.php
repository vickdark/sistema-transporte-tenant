<?php

namespace App\Actions\Migrations;

use App\Enums\MigrationModule;
use App\Enums\MigrationRunMode;
use App\Enums\MigrationRunStatus;
use App\Jobs\ExecuteMigrationRun;
use App\Models\CentralUser;
use App\Models\MigrationRun;
use App\Models\MigrationRunStep;
use App\Models\MigrationSource;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Crea una corrida con un paso por módulo (en orden de dependencias) y la manda a la cola.
 * Una empresa no puede tener dos corridas activas a la vez, y un módulo solo se migra si sus
 * dependencias van en la misma corrida o ya se migraron en una corrida real terminada.
 */
class StartMigrationRun
{
    /**
     * @param  list<MigrationModule>  $modules
     *
     * @throws ValidationException
     */
    public function handle(MigrationSource $source, Tenant $tenant, MigrationRunMode $mode, array $modules, CentralUser $startedBy): MigrationRun
    {
        $this->ensureTenantHasNoActiveRun($tenant);
        $this->ensureDependenciesAreMet($tenant, $modules);

        $modules = MigrationModule::inExecutionOrder($modules);

        $run = DB::transaction(function () use ($source, $tenant, $mode, $modules, $startedBy): MigrationRun {
            $run = MigrationRun::create([
                'migration_source_id' => $source->id,
                'tenant_id' => $tenant->id,
                'mode' => $mode,
                'status' => MigrationRunStatus::Pending,
                'modules' => array_map(fn (MigrationModule $module): string => $module->value, $modules),
                'started_by' => $startedBy->id,
            ]);

            foreach ($modules as $module) {
                $run->steps()->create(['module' => $module->value, 'status' => MigrationRunStatus::Pending]);
            }

            return $run;
        });

        ExecuteMigrationRun::dispatch($run);

        return $run;
    }

    /**
     * @throws ValidationException
     */
    private function ensureTenantHasNoActiveRun(Tenant $tenant): void
    {
        $hasActiveRun = MigrationRun::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', [MigrationRunStatus::Pending, MigrationRunStatus::Running])
            ->exists();

        if ($hasActiveRun) {
            throw ValidationException::withMessages([
                'tenant_id' => 'La empresa ya tiene una corrida en curso: espera a que termine o cancélala.',
            ]);
        }
    }

    /**
     * @param  list<MigrationModule>  $modules
     *
     * @throws ValidationException
     */
    private function ensureDependenciesAreMet(Tenant $tenant, array $modules): void
    {
        foreach ($modules as $module) {
            foreach ($module->dependsOn() as $dependency) {
                if (in_array($dependency, $modules, true) || $this->wasMigrated($tenant, $dependency)) {
                    continue;
                }

                throw ValidationException::withMessages([
                    'modules' => "{$module->label()} necesita {$dependency->label()}: inclúyelo en la corrida o mígralo antes en una corrida real.",
                ]);
            }
        }
    }

    private function wasMigrated(Tenant $tenant, MigrationModule $module): bool
    {
        return MigrationRunStep::query()
            ->where('module', $module->value)
            ->where('status', MigrationRunStatus::Completed)
            ->whereHas('run', fn ($query) => $query->where('tenant_id', $tenant->id)->where('mode', MigrationRunMode::Live))
            ->exists();
    }
}
