<?php

namespace Database\Factories;

use App\Enums\FuelType;
use App\Enums\VehicleClass;
use App\Models\Ally;
use App\Models\Supplier;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Vehículo activo de la empresa (sin proveedor ni aliado).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plate' => fake()->unique()->bothify('???###'),
            'internal_number' => fake()->numerify('###'),
            'current_mileage' => fake()->numberBetween(1000, 200000),
            'registration_date' => fake()->dateTimeBetween('-10 years', '-1 month')->format('Y-m-d'),
            'registration_place' => 'Cartagena',
            'model_year' => fake()->numberBetween(2012, 2025),
            'brand' => fake()->randomElement(['TOYOTA', 'CHEVROLET', 'NISSAN', 'HYUNDAI']),
            'line' => fake()->randomElement(['HIACE', 'NPR', 'URVAN', 'H1']),
            'vehicle_class' => VehicleClass::ClosedVan,
            'engine_number' => fake()->unique()->bothify('??########'),
            'chassis_number' => fake()->unique()->bothify('#??##########'),
            'color' => fake()->randomElement(['BLANCO', 'GRIS', 'PLATA']),
            'displacement' => 2700,
            'fuel_type' => FuelType::Diesel,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Vehicle $vehicle): void {
            $vehicle->plate = strtoupper($vehicle->plate);
        });
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /**
     * A nombre de un proveedor o de un aliado (de los dos si es mixto).
     */
    public function ownedBy(Supplier|Ally $owner): static
    {
        return $this->afterMaking(fn (Vehicle $vehicle) => $vehicle->assignOwner($owner));
    }
}
