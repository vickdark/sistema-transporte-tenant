<?php

namespace App\Actions\Migrations\Mappings;

use App\Enums\BaseRole;
use RuntimeException;

/**
 * Roles legacy (sisTransporte) → roles de la empresa, por id y nombre: si alguno de los dos no
 * coincide, la corrida se detiene (PLAN_MIGRACION.md §5.4). Un rol sin rol base equivalente se
 * crea como rol propio de la empresa con el nombre legacy.
 */
class LegacyRoleMap
{
    /**
     * @var array<int, array{name: string, base: BaseRole|null}>
     */
    private const ROLES = [
        1 => ['name' => 'Administrador', 'base' => BaseRole::Administrator],
        2 => ['name' => 'Administrativo', 'base' => BaseRole::Administrative],
        3 => ['name' => 'Conductor', 'base' => BaseRole::Driver],
        4 => ['name' => 'Creador', 'base' => null],
        5 => ['name' => 'Coordinador', 'base' => BaseRole::Coordinator],
        6 => ['name' => 'Cliente', 'base' => BaseRole::Client],
        7 => ['name' => 'Proveedores', 'base' => BaseRole::Supplier],
        8 => ['name' => 'Aliado', 'base' => BaseRole::Ally],
        9 => ['name' => 'Administrativo 2', 'base' => null],
        10 => ['name' => 'ASISTENTE DE COORDINACION', 'base' => BaseRole::CoordinationAssistant],
    ];

    /**
     * Rol base al que corresponde el rol legacy, o null si se crea como rol propio.
     *
     * @throws RuntimeException si el rol no está en el mapeo o su nombre cambió
     */
    public function baseRoleFor(int $legacyId, string $legacyName): ?BaseRole
    {
        $mapped = self::ROLES[$legacyId] ?? null;

        if ($mapped === null) {
            throw new RuntimeException("El rol legacy #{$legacyId} ({$legacyName}) no está en el mapeo de roles.");
        }

        if (mb_strtolower(trim($legacyName)) !== mb_strtolower($mapped['name'])) {
            throw new RuntimeException("El rol legacy #{$legacyId} se llama \"{$legacyName}\" y el mapeo espera \"{$mapped['name']}\".");
        }

        return $mapped['base'];
    }
}
