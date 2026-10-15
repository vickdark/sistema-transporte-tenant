<?php

namespace Database\Factories;

use App\Models\Ally;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ally>
 */
class AllyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => mb_strtoupper(fake()->unique()->name()),
            'nit' => fake()->unique()->numerify('##########'),
            'phone' => fake()->numerify('3#########'),
            'address' => fake()->streetAddress(),
            'email' => fake()->unique()->safeEmail(),
        ];
    }
}
