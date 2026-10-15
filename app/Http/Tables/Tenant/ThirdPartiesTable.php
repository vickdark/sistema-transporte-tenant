<?php

namespace App\Http\Tables\Tenant;

use App\Http\Tables\DataTable;
use App\Models\Ally;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Listado de aliados o de proveedores (mismas columnas, como en el legacy): búsqueda por nombre,
 * NIT, teléfono o correo y filtro por tipo (sencillo o mixto).
 *
 * @template TModel of Ally|Supplier
 *
 * @extends DataTable<TModel>
 */
abstract class ThirdPartiesTable extends DataTable
{
    /**
     * @return Builder<TModel>
     */
    abstract protected function baseQuery(): Builder;

    /**
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    abstract protected function whereMixed(Builder $query, bool $mixed): Builder;

    /**
     * @param  TModel  $model
     */
    abstract protected function twin(Model $model): Ally|Supplier|null;

    /**
     * Nombre de la ruta del registro gemelo (para enlazarlo cuando es mixto).
     */
    abstract protected function twinRoute(): string;

    abstract protected function nitLabel(): string;

    protected function query(): Builder
    {
        $search = $this->search();
        $type = $this->type();

        return $this->baseQuery()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('nit', "%{$search}%")
                ->orWhereLike('phone', "%{$search}%")
                ->orWhereLike('email', "%{$search}%")))
            ->when($type !== null, fn ($query) => $this->whereMixed($query, $type === 'mixed'));
    }

    protected function sortable(): array
    {
        return ['name' => 'name', 'nit' => 'nit'];
    }

    protected function defaultSort(): array
    {
        return ['name', 'asc'];
    }

    /**
     * @param  TModel  $model
     */
    protected function toRow(Model $model): array
    {
        $twin = $this->twin($model);
        $viewer = $this->request->user();

        return [
            'id' => $model->id,
            'name' => $model->name,
            'nit' => $model->nit,
            'phone' => $model->phone,
            'email' => $model->email,
            'is_mixed' => $twin !== null,
            'twin' => $twin ? ['name' => $twin->name, 'url' => $viewer->can('view', $twin) ? route($this->twinRoute(), $twin) : null] : null,
            'can' => [
                'update' => $viewer->can('update', $model),
                'link' => $viewer->can('link', $model),
            ],
        ];
    }

    protected function exportColumns(): array
    {
        return [
            'Nombre' => fn (Ally|Supplier $model) => $model->name,
            $this->nitLabel() => fn (Ally|Supplier $model) => $model->nit,
            'Teléfono' => fn (Ally|Supplier $model) => $model->phone,
            'Dirección' => fn (Ally|Supplier $model) => $model->address,
            'Correo' => fn (Ally|Supplier $model) => $model->email,
            'Tipo' => fn (Ally|Supplier $model) => $this->twin($model) ? 'Mixto' : 'Sencillo',
        ];
    }

    protected function filters(): array
    {
        return ['type' => $this->type()];
    }

    protected function filterSummary(): array
    {
        $type = match ($this->type()) {
            'mixed' => ['Tipo: Mixto'],
            'single' => ['Tipo: Sencillo'],
            default => [],
        };

        return [...parent::filterSummary(), ...$type];
    }

    /**
     * @return 'mixed'|'single'|null
     */
    private function type(): ?string
    {
        $type = $this->request->string('type')->value();

        return in_array($type, ['mixed', 'single'], true) ? $type : null;
    }
}
