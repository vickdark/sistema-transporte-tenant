<?php

namespace App\Actions\Migrations\Importers;

use App\Models\Ally;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * `aliados` y `proveedores` legacy → aliados y proveedores de la empresa, en pasadas por su
 * referencia circular:
 *
 * 1. Aliados y 2. proveedores (datos limpios: sin espacios sobrantes, correo en minúsculas).
 * 3. Vínculos mixtos: solo si el legacy los tiene en los dos sentidos (aliado ↔ proveedor).
 * 4. Reporte de registros repetidos (mismo NIT o nombre) dentro de cada módulo: se migran todos.
 * 5. Usuarios ya importados que pertenecen a un aliado o proveedor (se relee `usuarios`).
 */
class ThirdPartiesImporter implements LegacyImporter
{
    public const ALLIES_TABLE = 'aliados';

    public const SUPPLIERS_TABLE = 'proveedores';

    public function import(ImportContext $context): void
    {
        $legacyAllies = $context->legacy->table('aliados')
            ->select('id', 'nombre as name', 'nit', 'telefono as phone', 'direccion as address', 'email', 'proveedor_id as supplier_id')
            ->orderBy('id')
            ->get();
        $legacySuppliers = $context->legacy->table('proveedores')
            ->select('id', 'nombre as name', 'nit', 'telefono as phone', 'direccion as address', 'email', 'aliado_id as ally_id')
            ->orderBy('id')
            ->get();

        $context->total($legacyAllies->count() + $legacySuppliers->count());

        $allies = $this->importRecords($context, $legacyAllies, Ally::class, self::ALLIES_TABLE);
        $suppliers = $this->importRecords($context, $legacySuppliers, Supplier::class, self::SUPPLIERS_TABLE);

        $this->linkMixed($context, $legacyAllies, $legacySuppliers, $allies, $suppliers);
        $this->reportRepeated($context, $legacyAllies, self::ALLIES_TABLE, 'aliados');
        $this->reportRepeated($context, $legacySuppliers, self::SUPPLIERS_TABLE, 'proveedores');
        $this->linkUsers($context, $allies, $suppliers);

        $context->flush();
    }

    /**
     * @param  Collection<int, object>  $records
     * @param  class-string<Ally|Supplier>  $modelClass
     * @return array<int|string, int> id legacy => id nuevo
     */
    private function importRecords(ImportContext $context, Collection $records, string $modelClass, string $legacyTable): array
    {
        $ids = [];

        foreach ($records as $record) {
            $context->read();

            try {
                $ids[$record->id] = DB::transaction(fn (): int => $this->importRecord($context, $record, $modelClass, $legacyTable));
            } catch (QuarantinedRecord $quarantined) {
                $context->quarantine($legacyTable, $record->id, $quarantined);
            }

            $context->tick();
        }

        return $ids;
    }

    /**
     * @param  class-string<Ally|Supplier>  $modelClass
     */
    private function importRecord(ImportContext $context, object $record, string $modelClass, string $legacyTable): int
    {
        $name = preg_replace('/\s+/', ' ', trim((string) $record->name));

        if ($name === '') {
            throw new QuarantinedRecord('No tiene nombre.', ['nit' => $record->nit]);
        }

        $email = Str::lower(trim((string) $record->email)) ?: null;

        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $context->warning("El correo \"{$email}\" no es válido: se importa sin correo.", $legacyTable, $record->id);
            $email = null;
        }

        $model = $modelClass::query()->where('legacy_source', $legacyTable)->where('legacy_id', (string) $record->id)->first();
        $isNew = $model === null;

        $model ??= new $modelClass;
        $model->forceFill([
            'name' => $name,
            'nit' => trim((string) $record->nit) ?: null,
            'phone' => trim((string) $record->phone) ?: null,
            'address' => trim((string) $record->address) ?: null,
            'email' => $email,
            'legacy_source' => $legacyTable,
            'legacy_id' => (string) $record->id,
        ])->save();

        $isNew ? $context->inserted() : $context->updated();

        return $model->id;
    }

    /**
     * Vínculo mixto (proveedor → aliado). Fallar cerrado: un vínculo que el legacy no tiene en
     * los dos sentidos no se crea, se reporta.
     *
     * @param  Collection<int, object>  $legacyAllies
     * @param  Collection<int, object>  $legacySuppliers
     * @param  array<int|string, int>  $allies
     * @param  array<int|string, int>  $suppliers
     */
    private function linkMixed(ImportContext $context, Collection $legacyAllies, Collection $legacySuppliers, array $allies, array $suppliers): void
    {
        $allySupplier = $legacyAllies->pluck('supplier_id', 'id');

        // Se recalculan todos: así repetir la corrida refleja el estado actual del legacy.
        Supplier::query()->whereIn('id', $suppliers)->update(['ally_id' => null]);

        foreach ($legacySuppliers as $legacySupplier) {
            if ($legacySupplier->ally_id === null || ! isset($suppliers[$legacySupplier->id])) {
                continue;
            }

            if ((string) ($allySupplier[$legacySupplier->ally_id] ?? '') !== (string) $legacySupplier->id) {
                $context->warning(
                    "El proveedor apunta al aliado #{$legacySupplier->ally_id}, pero ese aliado no apunta de vuelta: no se vinculan como mixto.",
                    self::SUPPLIERS_TABLE,
                    $legacySupplier->id,
                );

                continue;
            }

            if (! isset($allies[$legacySupplier->ally_id])) {
                $context->warning("Su aliado #{$legacySupplier->ally_id} no se importó: no se vinculan como mixto.", self::SUPPLIERS_TABLE, $legacySupplier->id);

                continue;
            }

            Supplier::query()->whereKey($suppliers[$legacySupplier->id])->update(['ally_id' => $allies[$legacySupplier->ally_id]]);
        }
    }

    /**
     * Registros repetidos dentro del mismo módulo (mismo NIT, o mismo nombre sin distinguir
     * mayúsculas): se migran todos porque otros datos pueden apuntar a cualquiera; se avisa
     * para depurarlos.
     *
     * @param  Collection<int, object>  $records
     */
    private function reportRepeated(ImportContext $context, Collection $records, string $legacyTable, string $label): void
    {
        $groups = [
            'NIT' => $records->filter(fn (object $record): bool => trim((string) $record->nit) !== '')
                ->groupBy(fn (object $record): string => trim((string) $record->nit)),
            'nombre' => $records->groupBy(fn (object $record): string => Str::lower(preg_replace('/\s+/', ' ', trim((string) $record->name)))),
        ];

        foreach ($groups as $field => $grouped) {
            foreach ($grouped->filter(fn (Collection $group): bool => $group->count() > 1) as $value => $group) {
                $ids = $group->pluck('id')->map(fn ($id): string => "#{$id}")->implode(', ');

                $context->warning(
                    "Hay {$group->count()} {$label} con el mismo {$field} \"{$value}\" ({$ids}). Se migran todos; conviene depurarlos.",
                    $legacyTable,
                    $group->first()->id,
                    ['field' => $field, 'value' => $value, 'legacy_ids' => $group->pluck('id')->all()],
                );
            }
        }
    }

    /**
     * Usuarios ya importados que en el legacy pertenecen a un aliado o a un proveedor.
     *
     * @param  array<int|string, int>  $allies
     * @param  array<int|string, int>  $suppliers
     */
    private function linkUsers(ImportContext $context, array $allies, array $suppliers): void
    {
        $legacyUsers = $context->legacy->table('usuarios')
            ->select('ID as id', 'AliadoID as ally_id', 'ProveedorID as supplier_id')
            ->where(fn ($query) => $query->whereNotNull('AliadoID')->orWhereNotNull('ProveedorID'))
            ->get();

        foreach ($legacyUsers as $legacyUser) {
            User::query()
                ->where('legacy_source', UsersImporter::LEGACY_TABLE)
                ->where('legacy_id', (string) $legacyUser->id)
                ->update([
                    'ally_id' => $legacyUser->ally_id !== null ? ($allies[$legacyUser->ally_id] ?? null) : null,
                    'supplier_id' => $legacyUser->ally_id === null && $legacyUser->supplier_id !== null ? ($suppliers[$legacyUser->supplier_id] ?? null) : null,
                ]);
        }
    }
}
