<?php

namespace Database\Factories;

use App\Enums\MigrationRunMode;
use App\Enums\MigrationRunStatus;
use App\Models\MigrationRun;
use App\Models\MigrationSource;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MigrationRun>
 */
class MigrationRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'migration_source_id' => MigrationSource::factory(),
            'tenant_id' => Tenant::factory(),
            'mode' => MigrationRunMode::DryRun,
            'status' => MigrationRunStatus::Pending,
            'modules' => ['users'],
        ];
    }
}
