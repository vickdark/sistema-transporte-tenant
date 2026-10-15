<?php

namespace App\Http\Tables\Tenant;

use App\Enums\RouteStatus;
use App\Enums\RouteType;
use App\Http\Tables\DataTable;
use App\Models\TransportRoute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Listado de rutas: activas (incluidas las que están en mantenimiento) o inactivas, filtro por
 * tipo y búsqueda por inicio, fin o descripción.
 *
 * @extends DataTable<TransportRoute>
 */
class RoutesTable extends DataTable
{
    public function title(): string
    {
        return $this->showsInactive() ? 'Rutas inactivas' : 'Rutas';
    }

    protected function query(): Builder
    {
        $search = $this->search();
        $type = $this->type();

        return TransportRoute::query()
            ->when(
                $this->showsInactive(),
                fn ($query) => $query->where('status', RouteStatus::Inactive),
                fn ($query) => $query->where('status', '!=', RouteStatus::Inactive),
            )
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('start_point', "%{$search}%")
                ->orWhereLike('end_point', "%{$search}%")
                ->orWhereLike('description', "%{$search}%")))
            ->when($type, fn ($query) => $query->where('type', $type));
    }

    protected function sortable(): array
    {
        return [
            'name' => DB::raw("start_point || ' - ' || end_point"),
            'status' => 'status',
            'created_at' => 'created_at',
        ];
    }

    protected function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    /**
     * @param  TransportRoute  $model
     */
    protected function toRow(Model $model): array
    {
        $viewer = $this->request->user();

        return [
            'id' => $model->id,
            'name' => $model->name,
            'description' => $model->description,
            'type_label' => $model->type->label(),
            'status' => $model->status,
            'status_label' => $model->status->label(),
            'created_at' => $model->created_at?->toDateString(),
            'can' => [
                'update' => $viewer->can('update', $model),
                'toggle_active' => $viewer->can('toggleActive', $model),
            ],
        ];
    }

    protected function exportColumns(): array
    {
        return [
            'Ruta' => fn (TransportRoute $route) => $route->name,
            'Punto de inicio' => fn (TransportRoute $route) => $route->start_point,
            'Punto de fin' => fn (TransportRoute $route) => $route->end_point,
            'Descripción' => fn (TransportRoute $route) => $route->description,
            'Tipo' => fn (TransportRoute $route) => $route->type->label(),
            'Estado' => fn (TransportRoute $route) => $route->status->label(),
            'Creada' => fn (TransportRoute $route) => $route->created_at?->toDateString(),
        ];
    }

    protected function filters(): array
    {
        return ['status' => $this->showsInactive() ? 'inactive' : 'active', 'type' => $this->type()?->value];
    }

    protected function filterSummary(): array
    {
        $type = $this->type();

        return [...parent::filterSummary(), ...($type ? ["Tipo: {$type->label()}"] : [])];
    }

    private function showsInactive(): bool
    {
        return $this->request->string('status')->value() === 'inactive';
    }

    private function type(): ?RouteType
    {
        return RouteType::tryFrom($this->request->string('type')->value());
    }
}
