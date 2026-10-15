<?php

namespace Database\Factories;

use App\Models\Part;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Part>
 */
class PartFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Motor', 'Frenos', 'Aceite', 'Filtro de aire', 'Batería', 'Neumáticos', 'Suspensión', 'Correa de distribución']),
        ];
    }
}
