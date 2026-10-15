<?php

namespace Database\Factories;

use App\Models\Part;
use App\Models\Vehicle;
use App\Models\VehiclePart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehiclePart>
 */
class VehiclePartFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'part_id' => Part::factory(),
            'maintenance_interval_km' => fake()->randomElement([5000, 10000, 20000, 40000]),
        ];
    }
}
