<?php

namespace App\Models;

use App\Enums\MigrationRunStatus;
use Database\Factories\MigrationRunStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Paso de una corrida: un módulo (importador) con sus contadores.
 *
 * @property int $id
 * @property int $migration_run_id
 * @property string $module
 * @property MigrationRunStatus $status
 * @property int|null $total_count
 * @property int $read_count
 * @property int $inserted_count
 * @property int $updated_count
 * @property int $quarantined_count
 * @property int $skipped_count
 * @property string|null $error
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
class MigrationRunStep extends Model
{
    /** @use HasFactory<MigrationRunStepFactory> */
    use CentralConnection, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'migration_run_id',
        'module',
        'status',
        'total_count',
        'read_count',
        'inserted_count',
        'updated_count',
        'quarantined_count',
        'skipped_count',
        'error',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MigrationRunStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MigrationRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(MigrationRun::class, 'migration_run_id');
    }
}
