<?php

namespace Database\Factories;

use App\Models\Agreement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agreement>
 */
class AgreementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nit' => fake()->unique()->numerify('9########'),
            'business_name' => mb_strtoupper(fake()->company()).' SAS',
        ];
    }
}
