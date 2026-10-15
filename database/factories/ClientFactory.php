<?php

namespace Database\Factories;

use App\Enums\ClientType;
use App\Enums\IdentificationType;
use App\Models\Ally;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Cliente persona de la empresa (sin aliado).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ClientType::Person,
            'identification_type' => IdentificationType::CitizenId,
            'identification' => fake()->unique()->numerify('##########'),
            'first_names' => mb_strtoupper(fake()->firstName()),
            'last_names' => mb_strtoupper(fake()->lastName()),
            'city' => 'Cartagena',
            'address' => fake()->streetAddress(),
            'phone' => fake()->numerify('3#########'),
        ];
    }

    public function company(): static
    {
        return $this->state(fn (): array => [
            'type' => ClientType::Company,
            'identification_type' => IdentificationType::Nit,
            'identification' => fake()->unique()->numerify('9########'),
            'check_digit' => (string) fake()->numberBetween(0, 9),
            'first_names' => null,
            'last_names' => null,
            'business_name' => mb_strtoupper(fake()->company()).' SAS',
            'trade_name' => mb_strtoupper(fake()->company()),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function ownedBy(Ally $ally): static
    {
        return $this->state(fn (): array => ['ally_id' => $ally->id]);
    }
}
