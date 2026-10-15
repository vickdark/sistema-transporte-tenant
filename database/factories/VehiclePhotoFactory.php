<?php

namespace Database\Factories;

use App\Models\Vehicle;
use App\Models\VehiclePhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehiclePhoto>
 */
class VehiclePhotoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'path' => 'vehicles/photos/'.fake()->uuid().'.jpg',
        ];
    }
}
