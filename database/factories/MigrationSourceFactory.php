<?php

namespace Database\Factories;

use App\Enums\MigrationSourceType;
use App\Models\MigrationSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MigrationSource>
 */
class MigrationSourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Copia '.fake()->company(),
            'type' => MigrationSourceType::MysqlConnection,
            'credentials' => [
                'host' => '127.0.0.1',
                'port' => 3306,
                'database' => 'legacy_copy',
                'username' => 'readonly',
                'password' => 'secret',
            ],
        ];
    }
}
