<?php

namespace App\Jobs;

use App\Actions\Migrations\Importers\ImportContext;
use App\Actions\Migrations\Importers\LegacyImporter;
use App\Actions\Migrations\LegacyConnection;
use App\Actions\Migrations\MigrationRunCancelled;
use App\Enums\MigrationIssueSeverity;
use App\Enums\MigrationModule;
use App\Enums\MigrationRunMode;
use App\Enums\MigrationRunStatus;
use App\Models\MigrationRun;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Ejecuta una corrida de migración: verifica que la fuente sea de la empresa destino y corre sus
 * pasos en orden dentro del schema de la empresa.
 *
 * - Real: cada paso va en su propia transacción; si uno falla se revierte solo ese paso y la
 *   corrida se detiene (los anteriores quedan, y repetir la corrida es seguro: es idempotente).
 * - Simulación: toda la corrida va en una transacción que se revierte al final, así cada paso ve
 *   lo que importaron los anteriores pero nada queda escrito. Solo quedan contadores y reporte.
 */
class ExecuteMigrationRun implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public MigrationRun $run) {}

    public function handle(LegacyConnection $connections): void
    {
        $run = $this->run->fresh(['tenant', 'source']);

        // Cancelada antes de empezar.
        if ($run === null || $run->status !== MigrationRunStatus::Pending) {
            return;
        }

        $run->update(['status' => MigrationRunStatus::Running, 'started_at' => now()]);
        $legacy = null;

        try {
            $legacy = $connections->connect($run->source);
            $this->ensureSourceBelongsToTenant($legacy, $run->tenant);

            $run->tenant->run(function () use ($run, $legacy): void {
                if ($run->mode === MigrationRunMode::Live) {
                    $this->runSteps($run, $legacy);

                    return;
                }

                DB::beginTransaction();

                try {
                    $this->runSteps($run, $legacy);
                } finally {
                    DB::rollBack();
                }
            });

            $run->update(['status' => $this->finalStatus($run), 'finished_at' => now()]);
        } catch (Throwable $exception) {
            $this->markAsFailed($run, $exception);
        } finally {
            if ($legacy !== null) {
                $connections->disconnect($legacy);
            }
        }
    }

    /**
     * El worker se cayó o se agotó el tiempo: la corrida no puede quedar "en curso" para siempre.
     */
    public function failed(Throwable $exception): void
    {
        $run = $this->run->fresh();

        if ($run !== null && in_array($run->status, [MigrationRunStatus::Pending, MigrationRunStatus::Running], true)) {
            $this->markAsFailed($run, $exception);
        }
    }

    private function runSteps(MigrationRun $run, ConnectionInterface $legacy): void
    {
        foreach ($run->steps()->orderBy('id')->get() as $step) {
            $step->update(['status' => MigrationRunStatus::Running, 'started_at' => now()]);

            /** @var LegacyImporter $importer */
            $importer = app(MigrationModule::from($step->module)->importer());
            $context = new ImportContext($legacy, $run, $step);

            try {
                $run->mode === MigrationRunMode::Live
                    ? DB::transaction(fn () => $importer->import($context))
                    : $importer->import($context);

                $step->update(['status' => MigrationRunStatus::Completed, 'finished_at' => now()]);
            } catch (MigrationRunCancelled) {
                $step->update(['status' => MigrationRunStatus::Cancelled, 'finished_at' => now()]);

                return;
            } catch (Throwable $exception) {
                report($exception);
                $step->update(['status' => MigrationRunStatus::Failed, 'error' => $exception->getMessage(), 'finished_at' => now()]);

                return;
            }
        }
    }

    /**
     * Evita migrar la fuente de una empresa en otra: el NIT de `configuracion_empresa` debe ser
     * el de la empresa destino (se acepta que uno de los dos incluya el dígito de verificación).
     */
    private function ensureSourceBelongsToTenant(ConnectionInterface $legacy, Tenant $tenant): void
    {
        $legacyNit = (string) $legacy->table('configuracion_empresa')->orderBy('id')->value('nit');
        $digits = fn (?string $nit): string => (string) preg_replace('/\D/', '', (string) $nit);
        [$source, $target] = [$digits($legacyNit), $digits($tenant->nit)];

        $matches = $source !== '' && $target !== ''
            && ($source === $target || substr($source, 0, -1) === $target || substr($target, 0, -1) === $source);

        if (! $matches) {
            throw new RuntimeException(
                "El NIT de la fuente ({$legacyNit}) no coincide con el de la empresa {$tenant->name} (".($tenant->nit ?: 'sin NIT').').'
            );
        }
    }

    private function finalStatus(MigrationRun $run): MigrationRunStatus
    {
        $statuses = $run->steps()->pluck('status');

        return match (true) {
            $statuses->contains(MigrationRunStatus::Failed) => MigrationRunStatus::Failed,
            $statuses->contains(MigrationRunStatus::Cancelled) => MigrationRunStatus::Cancelled,
            default => MigrationRunStatus::Completed,
        };
    }

    private function markAsFailed(MigrationRun $run, Throwable $exception): void
    {
        $run->update(['status' => MigrationRunStatus::Failed, 'finished_at' => now()]);
        $run->steps()
            ->where('status', MigrationRunStatus::Running)
            ->update(['status' => MigrationRunStatus::Failed, 'finished_at' => now()]);
        $run->issues()->create([
            'severity' => MigrationIssueSeverity::Error,
            'message' => $exception->getMessage(),
        ]);
    }
}
