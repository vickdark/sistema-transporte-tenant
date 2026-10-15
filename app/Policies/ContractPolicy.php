<?php

namespace App\Policies;

use App\Enums\ContractKind;
use App\Enums\Permission;
use App\Models\Contract;
use App\Models\User;

/**
 * Contratos propios (permisos `contracts.*`) y de aliados (`ally_contracts.*`). Además del
 * permiso, el contrato debe ser visible para el usuario: los propios solo para usuarios de la
 * empresa; los de un aliado para los usuarios de ese aliado o para usuarios de la empresa.
 * Los vencidos no se editan. No se eliminan.
 */
class ContractPolicy
{
    public function viewAny(User $user, ContractKind $kind): bool
    {
        return $user->can(self::permission($kind, 'view')) && self::canWorkWith($user, $kind);
    }

    public function view(User $user, Contract $contract): bool
    {
        return $user->can(self::permission($contract->kind, 'view')) && self::isVisible($user, $contract);
    }

    public function create(User $user, ContractKind $kind): bool
    {
        return $user->can(self::permission($kind, 'create')) && self::canWorkWith($user, $kind);
    }

    public function update(User $user, Contract $contract): bool
    {
        return $user->can(self::permission($contract->kind, 'update'))
            && self::isVisible($user, $contract)
            && $contract->isEditable();
    }

    private static function permission(ContractKind $kind, string $action): string
    {
        return match ($kind) {
            ContractKind::Own => match ($action) {
                'view' => Permission::ContractsView->value,
                'create' => Permission::ContractsCreate->value,
                default => Permission::ContractsUpdate->value,
            },
            ContractKind::Ally => match ($action) {
                'view' => Permission::AllyContractsView->value,
                'create' => Permission::AllyContractsCreate->value,
                default => Permission::AllyContractsUpdate->value,
            },
        };
    }

    /**
     * Los contratos propios son de la empresa: un usuario de un aliado no los ve.
     */
    private static function canWorkWith(User $user, ContractKind $kind): bool
    {
        return $kind === ContractKind::Ally || $user->ally_id === null;
    }

    private static function isVisible(User $user, Contract $contract): bool
    {
        return match ($contract->kind) {
            ContractKind::Own => $user->ally_id === null,
            ContractKind::Ally => $user->ally_id === null || $user->ally_id === $contract->ally_id,
        };
    }
}
