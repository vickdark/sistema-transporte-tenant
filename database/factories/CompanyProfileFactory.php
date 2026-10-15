<?php

namespace Database\Factories;

use App\Models\CompanyProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyProfile>
 */
class CompanyProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 1,
            'address' => fake()->streetAddress(),
            'phone' => fake()->numerify('3#########'),
            'email' => fake()->companyEmail(),
            'service_type' => CompanyProfile::DEFAULT_SERVICE_TYPE,
            'territorial_code' => '213',
            'habilitation_resolution' => '0114',
            'habilitation_year' => '16',
        ];
    }
}
