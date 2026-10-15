<?php

namespace Database\Factories;

use App\Enums\MigrationRunStatus;
use App\Models\MigrationRun;
use App\Models\MigrationRunStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MigrationRunStep>
 */
class MigrationRunStepFactory extends Factory
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
            'module' => 'users',
            'status' => MigrationRunStatus::Pending,
        ];
    }
}
