<?php

namespace App\Actions\Migrations\Importers;

use App\Actions\Migrations\Mappings\LegacyPermissionMap;
use App\Actions\Migrations\Mappings\LegacyRoleMap;
use App\Enums\BaseRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles legacy → roles de la empresa. Los que tienen rol base equivalente se vinculan a él (la
 * empresa conserva el nombre que le haya dado); los demás se crean como roles propios. Los
 * permisos de cada rol quedan exactamente en lo que sale del mapeo de URLs; el Administrador
 * conserva todos.
 */
class RolesImporter implements LegacyImporter
{
    public const LEGACY_TABLE = 'roles';

    public function __construct(
        private LegacyRoleMap $roleMap,
        private LegacyPermissionMap $permissionMap,
    ) {}

    public function import(ImportContext $context): void
    {
        $this->ensureEveryLegacyUrlIsMapped($context);

        $grantedUrls = $context->legacy->table('rol_permisos')
            ->join('permisos', 'permisos.ID', '=', 'rol_permisos.permiso_id')
            ->select('rol_permisos.rol_id as role_id', 'permisos.url as url')
            ->get()
            ->groupBy('role_id')
            ->map(fn ($grants): array => $grants->pluck('url')->all());

        $legacyRoles = $context->legacy->table('roles')
            ->select('id', 'nombre as name', 'descripcion as description')
            ->orderBy('id')
            ->get();

        $context->total($legacyRoles->count());

        // Primero se valida el mapeo completo: un rol desconocido detiene el paso antes de escribir.
        $baseRoles = $legacyRoles->mapWithKeys(fn (object $legacyRole): array => [
            $legacyRole->id => $this->roleMap->baseRoleFor((int) $legacyRole->id, (string) $legacyRole->name),
        ]);

        foreach ($legacyRoles as $legacyRole) {
            $context->read();

            try {
                DB::transaction(function () use ($context, $legacyRole, $baseRoles, $grantedUrls): void {
                    $this->importRole($context, $legacyRole, $baseRoles[$legacyRole->id], $grantedUrls[$legacyRole->id] ?? []);
                });
            } catch (QuarantinedRecord $record) {
                $context->quarantine(self::LEGACY_TABLE, $legacyRole->id, $record);
            }

            $context->tick();
        }

        $this->reportUrlsOfPendingModules($context, $grantedUrls->flatten()->unique()->all());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $context->flush();
    }

    /**
     * @param  list<string>  $legacyUrls
     */
    private function importRole(ImportContext $context, object $legacyRole, ?BaseRole $baseRole, array $legacyUrls): void
    {
        $legacyId = (string) $legacyRole->id;
        $role = $baseRole !== null
            ? Role::query()->where('base_key', $baseRole->value)->first()
            : Role::query()->where('legacy_source', self::LEGACY_TABLE)->where('legacy_id', $legacyId)->first();

        if ($role === null && $baseRole !== null) {
            throw new RuntimeException("La empresa no tiene el rol base {$baseRole->defaultName()}: ejecuta tenants:sync-access.");
        }

        $isNew = $role === null;

        if ($isNew) {
            if (Role::query()->where('name', $legacyRole->name)->exists()) {
                throw new QuarantinedRecord(
                    "Ya existe en la empresa un rol llamado \"{$legacyRole->name}\" que no viene del legacy.",
                    ['name' => $legacyRole->name],
                );
            }

            /** @var Role $role */
            $role = Role::create([
                'name' => $legacyRole->name,
                'guard_name' => User::GUARD,
                'description' => $legacyRole->description,
            ]);
        }

        // Si el mapeo cambió, la referencia legacy pasa al rol que corresponde ahora.
        Role::query()
            ->where('legacy_source', self::LEGACY_TABLE)
            ->where('legacy_id', $legacyId)
            ->whereKeyNot($role->id)
            ->update(['legacy_source' => null, 'legacy_id' => null]);

        $role->forceFill(['legacy_source' => self::LEGACY_TABLE, 'legacy_id' => $legacyId])->save();

        if (! $role->is_protected) {
            $role->syncPermissions($this->permissionMap->permissionsFor($legacyUrls));
        }

        $isNew ? $context->inserted() : $context->updated();
    }

    /**
     * Una URL que el mapeo no conoce podría ser un permiso que nadie revisó: se detiene el paso.
     */
    private function ensureEveryLegacyUrlIsMapped(ImportContext $context): void
    {
        $unknown = $context->legacy->table('permisos')
            ->orderBy('url')
            ->pluck('url')
            ->reject(fn (string $url): bool => $this->permissionMap->isKnown($url));

        if ($unknown->isNotEmpty()) {
            throw new RuntimeException('Hay permisos legacy sin mapeo: '.$unknown->implode(', ').'. Agrégalos a LegacyPermissionMap.');
        }
    }

    /**
     * @param  list<string>  $grantedUrls
     */
    private function reportUrlsOfPendingModules(ImportContext $context, array $grantedUrls): void
    {
        $pending = collect($grantedUrls)
            ->groupBy(fn (string $url): ?string => $this->permissionMap->pendingModule($url))
            ->forget('')
            ->map(fn ($urls): int => $urls->count())
            ->sortKeys();

        if ($pending->isEmpty()) {
            return;
        }

        $context->warning(
            'Permisos legacy de módulos que aún no existen; no se otorgaron y se asignarán cuando se construya cada módulo: '
                .$pending->map(fn (int $count, string $module): string => "{$module} ({$count})")->implode(', ').'.',
            payload: ['modules' => $pending->all()],
        );
    }
}
