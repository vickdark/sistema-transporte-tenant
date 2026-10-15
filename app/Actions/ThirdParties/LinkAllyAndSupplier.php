<?php

namespace App\Actions\ThirdParties;

use App\Models\Ally;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Convertir" y "Revertir" de aliados y proveedores, como en el legacy:
 *
 * - Convertir: si el registro no tiene gemelo en el otro módulo se crea copiando sus datos; si ya
 *   existe uno con el mismo NIT sin vincular, se actualiza con estos datos y se vincula. Queda mixto.
 * - Revertir: rompe el vínculo; los dos registros siguen existiendo por separado.
 *
 * Los vehículos y conductores del registro se asocian (o desasocian) al otro perfil cuando
 * existan esos módulos (2.6 vehículos y 2.7 empleados), dentro de esta misma acción.
 *
 * @phpstan-type LinkResult array{level: 'success'|'warning', message: string}
 */
class LinkAllyAndSupplier
{
    /**
     * @return LinkResult
     *
     * @throws ValidationException
     */
    public function convertSupplierToAlly(Supplier $supplier): array
    {
        return DB::transaction(function () use ($supplier): array {
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($supplier->id);

            if ($supplier->isMixed()) {
                return ['level' => 'warning', 'message' => 'Este proveedor ya fue convertido en aliado.'];
            }

            $ally = $this->unlinkedTwin(Ally::query()->whereDoesntHave('supplier'), Ally::query()->whereHas('supplier'), $supplier->nit, 'aliado');
            $restored = $ally !== null;

            $ally ??= new Ally(['nit' => $supplier->nit]);
            $ally->fill($supplier->only(['name', 'phone', 'address', 'email']))->save();
            $supplier->forceFill(['ally_id' => $ally->id])->save();

            return [
                'level' => 'success',
                'message' => $restored ? 'Se ha restablecido la relación con el aliado existente.' : 'El proveedor fue convertido en aliado correctamente.',
            ];
        });
    }

    /**
     * @return LinkResult
     *
     * @throws ValidationException
     */
    public function convertAllyToSupplier(Ally $ally): array
    {
        return DB::transaction(function () use ($ally): array {
            $ally = Ally::query()->lockForUpdate()->findOrFail($ally->id);

            if ($ally->isMixed()) {
                return ['level' => 'warning', 'message' => 'Este aliado ya fue convertido en proveedor.'];
            }

            $supplier = $this->unlinkedTwin(Supplier::query()->whereNull('ally_id'), Supplier::query()->whereNotNull('ally_id'), $ally->nit, 'proveedor');
            $restored = $supplier !== null;

            $supplier ??= new Supplier(['nit' => $ally->nit]);
            $supplier->fill($ally->only(['name', 'phone', 'address', 'email']));
            $supplier->ally_id = $ally->id;
            $supplier->save();

            return [
                'level' => 'success',
                'message' => $restored ? 'Se ha restablecido la relación con el proveedor existente.' : 'El aliado fue convertido en proveedor correctamente.',
            ];
        });
    }

    /**
     * @return LinkResult
     */
    public function revertSupplier(Supplier $supplier): array
    {
        if (! $supplier->isMixed()) {
            return ['level' => 'warning', 'message' => 'Este proveedor no está registrado como aliado.'];
        }

        $supplier->forceFill(['ally_id' => null])->save();

        return ['level' => 'success', 'message' => 'Se ha revertido la conversión a aliado correctamente.'];
    }

    /**
     * @return LinkResult
     */
    public function revertAlly(Ally $ally): array
    {
        $supplier = $ally->supplier;

        if ($supplier === null) {
            return ['level' => 'warning', 'message' => 'Este aliado no está registrado como proveedor.'];
        }

        $supplier->forceFill(['ally_id' => null])->save();

        return ['level' => 'success', 'message' => 'Se ha revertido la conversión a proveedor correctamente.'];
    }

    /**
     * Registro del otro módulo con el mismo NIT que todavía no está vinculado. Si el único con ese
     * NIT ya es mixto con otro registro, no se le roba el vínculo (el legacy sí lo hacía y
     * dejaba vínculos cruzados).
     *
     * @template TModel of Ally|Supplier
     *
     * @param  Builder<TModel>  $unlinked
     * @param  Builder<TModel>  $linked
     * @return TModel|null
     *
     * @throws ValidationException
     */
    private function unlinkedTwin($unlinked, $linked, ?string $nit, string $label): Ally|Supplier|null
    {
        if ($nit === null || $nit === '') {
            return null;
        }

        $twin = $unlinked->where('nit', $nit)->orderBy('id')->lockForUpdate()->first();

        if ($twin === null && $linked->where('nit', $nit)->exists()) {
            throw ValidationException::withMessages([
                'link' => "Ya existe un {$label} con el NIT {$nit} que es mixto con otro registro. Revierte ese vínculo primero.",
            ]);
        }

        return $twin;
    }
}
