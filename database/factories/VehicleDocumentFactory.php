<?php

namespace Database\Factories;

use App\Enums\VehicleDocumentType;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleDocument>
 */
class VehicleDocumentFactory extends Factory
{
    /**
     * SOAT vigente.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'type' => VehicleDocumentType::Soat,
            'number' => fake()->numerify('########'),
            'issued_on' => today()->subMonths(2)->toDateString(),
            'expires_on' => today()->addMonths(10)->toDateString(),
        ];
    }

    public function expiringIn(int $days): static
    {
        return $this->state(fn (): array => ['expires_on' => today()->addDays($days)->toDateString()]);
    }

    public function replaced(): static
    {
        return $this->state(fn (): array => ['replaced_at' => now()]);
    }
}
