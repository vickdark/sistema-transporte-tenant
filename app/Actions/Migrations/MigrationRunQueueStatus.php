<?php

namespace App\Actions\Migrations;

use App\Jobs\ExecuteMigrationRun;
use App\Models\MigrationRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Por qué una corrida sigue en cola: si hay algún worker vivo (latido que registran los workers en
 * cada vuelta, ver AppServiceProvider), si su trabajo está en la cola, cuántos tiene por delante,
 * si un worker lo tomó y no lo empezó, o si falló antes de empezar. Con la cola `database` se
 * revisa la tabla de trabajos; con otras colas solo se sabe del latido.
 *
 * @phpstan-type QueueStatus array{
 *     reason: string, reason_code: string, waiting_since: string|null, connection: string, queue: string,
 *     worker_last_seen: string|null, worker_active: bool, jobs_ahead: int|null, reserved_at: string|null, failed_error: string|null
 * }
 */
class MigrationRunQueueStatus
{
    /**
     * Clave de caché del último latido de un worker.
     */
    public const HEARTBEAT_KEY = 'queue:worker-heartbeat';

    /**
     * Un worker esperando trabajo da una vuelta cada pocos segundos: sin latido en este tiempo no hay worker.
     */
    public const WORKER_ACTIVE_SECONDS = 60;

    /**
     * @return QueueStatus
     */
    public function for(MigrationRun $run): array
    {
        $connection = (string) config('queue.default');
        $queue = (string) (config("queue.connections.{$connection}.queue") ?? 'default');
        $lastSeen = Cache::get(self::HEARTBEAT_KEY);
        $lastSeen = $lastSeen ? Carbon::parse($lastSeen) : null;
        $workerActive = $lastSeen !== null && $lastSeen->gt(now()->subSeconds(self::WORKER_ACTIVE_SECONDS));
        $usesDatabase = config("queue.connections.{$connection}.driver") === 'database';

        $job = $usesDatabase ? $this->queuedJob($connection, $run) : null;
        $failedError = $this->failedError($run);

        [$code, $reason] = match (true) {
            $failedError !== null => ['failed', 'El trabajo de la corrida falló antes de empezar. Revisa el error y crea una corrida nueva.'],
            $job !== null && $job['reserved_at'] !== null => ['reserved', 'Un worker tomó la corrida pero no la empezó: probablemente se detuvo a mitad (se cerró la terminal o se reinició el servidor). Vuelve a arrancar el worker; la tomará cuando venza su reserva.'],
            $usesDatabase && $job === null => ['missing', 'La corrida no está en la cola de trabajos: no hay nada que un worker pueda tomar. Cancélala y crea una nueva.'],
            ! $workerActive => ['no_worker', $lastSeen === null
                ? 'No hay ningún worker procesando la cola: nunca se ha visto uno en este servidor.'
                : "No hay ningún worker procesando la cola: el último se vio {$lastSeen->locale('es')->diffForHumans()}."],
            ($job['ahead'] ?? 0) > 0 => ['busy', "Hay un worker activo, pero está ocupado: hay {$job['ahead']} trabajo(s) antes en la cola."],
            default => ['should_start', 'Hay un worker activo y la corrida es la siguiente: debería empezar en unos segundos. Si no, revisa que el worker escuche esta conexión y cola.'],
        };

        return [
            'reason' => $reason,
            'reason_code' => $code,
            'waiting_since' => $run->created_at?->toIso8601String(),
            'connection' => $connection,
            'queue' => $queue,
            'worker_last_seen' => $lastSeen?->toIso8601String(),
            'worker_active' => $workerActive,
            'jobs_ahead' => $job['ahead'] ?? null,
            'reserved_at' => $job['reserved_at'] ?? null,
            'failed_error' => $failedError,
        ];
    }

    /**
     * El trabajo de la corrida en la tabla de la cola, con cuántos trabajos tiene por delante.
     *
     * @return array{ahead: int, reserved_at: string|null}|null
     */
    private function queuedJob(string $connection, MigrationRun $run): ?array
    {
        $table = DB::connection(config("queue.connections.{$connection}.connection"))->table(config("queue.connections.{$connection}.table", 'jobs'));

        $job = (clone $table)
            ->where('payload', 'like', '%'.class_basename(ExecuteMigrationRun::class).'%')
            ->get(['id', 'queue', 'payload', 'reserved_at'])
            ->first(fn (object $row): bool => self::runIdOf($row->payload) === $run->id);

        if ($job === null) {
            return null;
        }

        return [
            'ahead' => (clone $table)->where('queue', $job->queue)->where('id', '<', $job->id)->count(),
            'reserved_at' => $job->reserved_at ? Carbon::createFromTimestamp($job->reserved_at)->toIso8601String() : null,
        ];
    }

    /**
     * Primera línea del error si el trabajo de la corrida está entre los fallidos.
     */
    private function failedError(MigrationRun $run): ?string
    {
        $failed = config('queue.failed');

        if (($failed['driver'] ?? null) === null || ! str_contains((string) $failed['driver'], 'database')) {
            return null;
        }

        $row = DB::connection($failed['database'] ?? null)->table($failed['table'] ?? 'failed_jobs')
            ->where('payload', 'like', '%'.class_basename(ExecuteMigrationRun::class).'%')
            ->latest('id')
            ->get(['payload', 'exception'])
            ->first(fn (object $row): bool => self::runIdOf($row->payload) === $run->id);

        return $row ? Str::before((string) $row->exception, "\n") : null;
    }

    /**
     * Id de la corrida dentro del trabajo serializado (sin deserializarlo).
     */
    private static function runIdOf(string $payload): ?int
    {
        $command = (string) data_get(json_decode($payload, true), 'data.command', '');

        return preg_match('/MigrationRun";s:2:"id";i:(\d+);/', $command, $matches) === 1 ? (int) $matches[1] : null;
    }
}
