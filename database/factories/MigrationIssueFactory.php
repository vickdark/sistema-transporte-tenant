<?php

namespace Database\Factories;

use App\Enums\MigrationIssueSeverity;
use App\Models\MigrationIssue;
use App\Models\MigrationRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MigrationIssue>
 */
class MigrationIssueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'migration_run_id' => MigrationRun::factory(),
            'severity' => MigrationIssueSeverity::Quarantine,
            'legacy_table' => 'usuarios',
            'legacy_id' => (string) fake()->numberBetween(1, 500),
            'message' => 'Rol legacy sin mapeo.',
        ];
    }
}
