<?php

namespace App\Actions\Contracts;

use App\Enums\ContractKind;
use App\Models\Ally;
use App\Models\Client;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * A quién pertenecen los contratos con los que se trabaja en una petición:
 *
 * - Contratos propios (rutas `contracts.*`): de la empresa.
 * - Contratos de aliados (rutas `ally-contracts.*`): del aliado del usuario si pertenece a uno;
 *   si es un usuario de la empresa, del aliado que eligió (se guarda en su sesión, como en el
 *   legacy: "Gestionando información del aliado X").
 */
final class ContractOwner
{
    public const SESSION_KEY = 'ally_contracts.ally_id';

    public function __construct(
        public readonly ContractKind $kind,
        public readonly ?int $allyId,
    ) {}

    public static function fromRequest(Request $request): self
    {
        if (! $request->routeIs('ally-contracts.*')) {
            return new self(ContractKind::Own, null);
        }

        $user = $request->user();
        $allyId = $user->ally_id ?? $request->session()->get(self::SESSION_KEY);

        return new self(ContractKind::Ally, $allyId !== null ? (int) $allyId : null);
    }

    /**
     * Contratos de aliados sin aliado elegido: hay que elegirlo antes de seguir.
     */
    public function needsAlly(): bool
    {
        return $this->kind === ContractKind::Ally && $this->allyId === null;
    }

    public function ally(): ?Ally
    {
        return $this->allyId !== null ? Ally::query()->find($this->allyId) : null;
    }

    /**
     * @template TQuery of Builder<Contract>|Relation<Contract, *, *>
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public function contracts(Builder|Relation $query): Builder|Relation
    {
        return $query->ownedBy($this->kind, $this->allyId);
    }

    /**
     * Clientes a los que se les puede hacer un contrato: activos y del mismo dueño.
     *
     * @return Builder<Client>
     */
    public function clients(): Builder
    {
        return Client::query()
            ->where('is_active', true)
            ->when($this->kind === ContractKind::Own, fn ($query) => $query->whereNull('ally_id'), fn ($query) => $query->where('ally_id', $this->allyId));
    }

    public function routePrefix(): string
    {
        return $this->kind === ContractKind::Own ? 'contracts' : 'ally-contracts';
    }
}
