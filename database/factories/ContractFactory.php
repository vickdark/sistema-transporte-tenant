<?php

namespace Database\Factories;

use App\Enums\ContractKind;
use App\Enums\ContractPurpose;
use App\Models\Client;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Contrato propio vigente (del 1 de enero al 31 de diciembre del año actual).
 *
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => ContractKind::Own,
            'number' => fn (): int => (int) Contract::query()->where('kind', ContractKind::Own)->max('number') + 1,
            'client_id' => Client::factory(),
            'purpose' => ContractPurpose::Business,
            'starts_on' => today()->startOfYear(),
            'ends_on' => today()->endOfYear(),
            'responsible_identification' => fake()->numerify('##########'),
            'responsible_name' => fake()->name(),
            'responsible_email' => fake()->safeEmail(),
            'responsible_phone' => fake()->numerify('3#########'),
        ];
    }

    /**
     * Contrato de aliado: el aliado es el dueño del cliente.
     */
    public function forAlly(Client $client): static
    {
        return $this->state(fn (): array => [
            'kind' => ContractKind::Ally,
            'number' => (int) Contract::query()->where('kind', ContractKind::Ally)->max('number') + 1,
            'client_id' => $client->id,
            'ally_id' => $client->ally_id,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['starts_on' => today()->subYear()->startOfYear(), 'ends_on' => today()->subYear()->endOfYear()]);
    }
}
