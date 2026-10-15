<?php

namespace App\Actions\Migrations\Importers;

use App\Actions\Migrations\LegacyFiles;
use App\Actions\Migrations\LegacyFilesConnection;
use App\Actions\Migrations\MigrationRunCancelled;
use App\Enums\MigrationIssueSeverity;
use App\Enums\MigrationRunMode;
use App\Enums\MigrationRunStatus;
use App\Models\MigrationRun;
use App\Models\MigrationRunStep;
use Illuminate\Database\ConnectionInterface;

/**
 * Lo que un importador necesita durante su paso: la conexión de solo lectura a la fuente, los
 * contadores del paso y el registro de cuarentena y advertencias. Todo lo de control se escribe
 * por la conexión central, así sobrevive aunque el paso se revierta (simulación o error).
 */
class ImportContext
{
    private int $read = 0;

    private int $inserted = 0;

    private int $updated = 0;

    private int $quarantined = 0;

    private int $skipped = 0;

    private float $lastFlushAt = 0;

    /**
     * Cada cuánto (en segundos) se guardan los contadores mientras corre el paso.
     */
    private const FLUSH_INTERVAL = 0.5;

    private ?LegacyFiles $files = null;

    public function __construct(
        public readonly ConnectionInterface $legacy,
        public readonly MigrationRun $run,
        public readonly MigrationRunStep $step,
    ) {}

    /**
     * Archivos del legacy por el acceso SFTP de la fuente (sin copiar nada si es simulación). Si
     * la fuente tiene acceso configurado pero no se puede conectar, el paso falla.
     */
    public function files(): LegacyFiles
    {
        return $this->files ??= new LegacyFiles(
            $this->run->source ? app(LegacyFilesConnection::class)->connect($this->run->source) : null,
            $this->run->mode === MigrationRunMode::DryRun,
        );
    }

    /**
     * Registros que el paso va a leer, para que el panel muestre el progreso desde el inicio.
     */
    public function total(int $count): void
    {
        $this->step->update(['total_count' => $count]);
    }

    public function read(int $count = 1): void
    {
        $this->read += $count;
    }

    public function inserted(): void
    {
        $this->inserted++;
    }

    public function updated(): void
    {
        $this->updated++;
    }

    public function skipped(): void
    {
        $this->skipped++;
    }

    public function quarantine(string $legacyTable, int|string $legacyId, QuarantinedRecord $record): void
    {
        $this->quarantined++;

        $this->issue(MigrationIssueSeverity::Quarantine, $record->getMessage(), $legacyTable, $legacyId, $record->payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function warning(string $message, ?string $legacyTable = null, int|string|null $legacyId = null, array $payload = []): void
    {
        $this->issue(MigrationIssueSeverity::Warning, $message, $legacyTable, $legacyId, $payload);
    }

    /**
     * Los importadores la llaman después de cada registro: guarda el progreso como máximo cada
     * FLUSH_INTERVAL segundos, para verlo en vivo sin escribir en cada fila.
     *
     * @throws MigrationRunCancelled
     */
    public function tick(): void
    {
        if (microtime(true) - $this->lastFlushAt >= self::FLUSH_INTERVAL) {
            $this->flush();
        }
    }

    /**
     * Guarda los contadores del paso (para el progreso en vivo) y detiene el paso si el
     * administrador canceló la corrida.
     *
     * @throws MigrationRunCancelled
     */
    public function flush(): void
    {
        $this->lastFlushAt = microtime(true);

        $this->step->update([
            'read_count' => $this->read,
            'inserted_count' => $this->inserted,
            'updated_count' => $this->updated,
            'quarantined_count' => $this->quarantined,
            'skipped_count' => $this->skipped,
        ]);

        if (MigrationRun::query()->whereKey($this->run->id)->value('status') === MigrationRunStatus::Cancelled) {
            throw new MigrationRunCancelled;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function issue(MigrationIssueSeverity $severity, string $message, ?string $legacyTable, int|string|null $legacyId, array $payload): void
    {
        $this->run->issues()->create([
            'migration_run_step_id' => $this->step->id,
            'severity' => $severity,
            'legacy_table' => $legacyTable,
            'legacy_id' => $legacyId !== null ? (string) $legacyId : null,
            'message' => $message,
            'payload' => $payload === [] ? null : $payload,
        ]);
    }
}
