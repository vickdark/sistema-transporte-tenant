<?php

namespace App\Http\Tables\Tenant;

use App\Http\Tables\DataTable;
use App\Models\Agreement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Listado de convenios con sus vehículos (cantidad y placas), con búsqueda por NIT, razón social o
 * placa. Los más recientes primero, como el legacy.
 *
 * @extends DataTable<Agreement>
 */
class AgreementsTable extends DataTable
{
    public function title(): string
    {
        return 'Convenios';
    }

    protected function query(): Builder
    {
        $search = $this->search();

        return Agreement::query()
            ->withCount('vehicles')
            ->with(['vehicles' => fn ($query) => $query->select('id', 'plate', 'agreement_id')->orderBy('plate')])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('nit', "%{$search}%")
                ->orWhereLike('business_name', "%{$search}%")
                ->orWhereHas('vehicles', fn ($query) => $query->whereLike('plate', "%{$search}%"))));
    }

    protected function sortable(): array
    {
        return [
            'business_name' => 'business_name',
            'nit' => 'nit',
            'vehicles_count' => 'vehicles_count',
            'created_at' => 'created_at',
        ];
    }

    protected function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    /**
     * @param  Agreement  $model
     */
    protected function toRow(Model $model): array
    {
        $viewer = $this->request->user();

        return [
            'id' => $model->id,
            'nit' => $model->nit,
            'business_name' => $model->business_name,
            'vehicles_count' => $model->vehicles_count,
            'plates' => $model->vehicles->pluck('plate')->all(),
            'can' => [
                'update' => $viewer->can('update', $model),
                'delete' => $viewer->can('delete', $model),
            ],
        ];
    }

    protected function exportColumns(): array
    {
        return [
            'NIT' => fn (Agreement $agreement) => $agreement->nit,
            'Razón social' => fn (Agreement $agreement) => $agreement->business_name,
            'Vehículos' => fn (Agreement $agreement) => $agreement->vehicles_count,
            'Placas' => fn (Agreement $agreement) => $agreement->vehicles->pluck('plate')->implode(', '),
        ];
    }
}
