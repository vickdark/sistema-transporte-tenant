<?php

namespace Database\Factories;

use App\Enums\RouteStatus;
use App\Enums\RouteType;
use App\Models\TransportRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransportRoute>
 */
class TransportRouteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'start_point' => fake()->unique()->city(),
            'end_point' => fake()->city(),
            'description' => fake()->sentence(),
            'type' => RouteType::Urban,
            'status' => RouteStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => RouteStatus::Inactive]);
    }
}
