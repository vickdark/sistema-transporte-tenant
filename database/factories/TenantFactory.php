<?php

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'nit' => fake()->unique()->numerify('#########-#'),
            'status' => TenantStatus::Active,
            'email' => fake()->companyEmail(),
            'phone' => fake()->numerify('3#########'),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
        ];
    }

    /**
     * Indicate that the tenant is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TenantStatus::Suspended,
            'suspended_at' => now(),
        ]);
    }
}
