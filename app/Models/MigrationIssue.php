<?php

namespace App\Models;

use App\Enums\MigrationIssueSeverity;
use Database\Factories\MigrationIssueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Registro en cuarentena, error o advertencia de una corrida, con referencia al registro legacy.
 *
 * @property int $id
 * @property int $migration_run_id
 * @property int|null $migration_run_step_id
 * @property MigrationIssueSeverity $severity
 * @property string|null $legacy_table
 * @property string|null $legacy_id
 * @property string $message
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $resolved_at
 */
class MigrationIssue extends Model
{
    /** @use HasFactory<MigrationIssueFactory> */
    use CentralConnection, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'migration_run_id',
        'migration_run_step_id',
        'severity',
        'legacy_table',
        'legacy_id',
        'message',
        'payload',
        'resolved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'severity' => MigrationIssueSeverity::class,
            'payload' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MigrationRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(MigrationRun::class, 'migration_run_id');
    }

    /**
     * @return BelongsTo<MigrationRunStep, $this>
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(MigrationRunStep::class, 'migration_run_step_id');
    }
}
