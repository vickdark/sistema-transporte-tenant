<?php

namespace App\Models;

use App\Enums\MigrationRunMode;
use App\Enums\MigrationRunStatus;
use Database\Factories\MigrationRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Corrida de migración de una fuente legacy hacia un tenant.
 *
 * @property int $id
 * @property int $migration_source_id
 * @property string $tenant_id
 * @property MigrationRunMode $mode
 * @property MigrationRunStatus $status
 * @property list<string> $modules
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
class MigrationRun extends Model
{
    /** @use HasFactory<MigrationRunFactory> */
    use CentralConnection, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'migration_source_id',
        'tenant_id',
        'mode',
        'status',
        'modules',
        'started_by',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => MigrationRunMode::class,
            'status' => MigrationRunStatus::class,
            'modules' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MigrationSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(MigrationSource::class, 'migration_source_id');
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<CentralUser, $this>
     */
    public function starter(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'started_by');
    }

    /**
     * @return HasMany<MigrationRunStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(MigrationRunStep::class);
    }

    /**
     * @return HasMany<MigrationIssue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(MigrationIssue::class);
    }
}
