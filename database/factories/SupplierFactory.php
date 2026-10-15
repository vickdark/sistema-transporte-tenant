<?php

namespace Database\Factories;

use App\Models\Ally;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => mb_strtoupper(fake()->unique()->company()),
            'nit' => fake()->unique()->numerify('9########'),
            'phone' => fake()->numerify('3#########'),
            'address' => fake()->streetAddress(),
            'email' => fake()->unique()->companyEmail(),
        ];
    }

    /**
     * Proveedor mixto: vinculado a su registro como aliado, con los mismos datos.
     */
    public function mixed(?Ally $ally = null): static
    {
        return $this->state(function (array $attributes) use ($ally): array {
            $ally ??= Ally::factory()->create(array_intersect_key($attributes, array_flip(['name', 'nit', 'phone', 'address', 'email'])));

            return ['ally_id' => $ally->id];
        });
    }
}
