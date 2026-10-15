<?php

namespace App\Http\Tables\Tenant;

use App\Http\Tables\DataTable;
use App\Models\Part;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de partes con cuántos vehículos activos (de los que ve el usuario) tienen cada una y
 * su kilometraje entre mantenimientos: promedio, mínimo y máximo (como el legacy).
 *
 * @extends DataTable<Part>
 */
class PartsTable extends DataTable
{
    public function title(): string
    {
        return 'Partes de vehículos';
    }

    protected function query(): Builder
    {
        $search = $this->search();
        $activeVehicles = fn (Builder $query) => $query->whereHas('vehicle', fn (Builder $vehicle) => $vehicle->where('is_active', true)->visibleTo($this->request->user()));

        return Part::query()
            ->withCount(['vehicleParts as vehicles_count' => $activeVehicles])
            ->withAvg(['vehicleParts as average_interval_km' => $activeVehicles], 'maintenance_interval_km')
            ->withMin(['vehicleParts as min_interval_km' => $activeVehicles], 'maintenance_interval_km')
            ->withMax(['vehicleParts as max_interval_km' => $activeVehicles], 'maintenance_interval_km')
            ->when($search !== '', fn ($query) => $query->whereLike('name', "%{$search}%"));
    }

    protected function sortable(): array
    {
        return [
            'name' => 'name',
            'vehicles_count' => 'vehicles_count',
        ];
    }

    protected function defaultSort(): array
    {
        return ['name', 'asc'];
    }

    /**
     * @param  Part  $model
     */
    protected function toRow(Model $model): array
    {
        return [
            'id' => $model->id,
            'name' => $model->name,
            'vehicles_count' => $model->vehicles_count,
            'average_interval_km' => $model->average_interval_km !== null ? (int) round((float) $model->average_interval_km) : null,
            'min_interval_km' => $model->min_interval_km,
            'max_interval_km' => $model->max_interval_km,
        ];
    }

    protected function exportColumns(): array
    {
        return [
            'Parte' => fn (Part $part) => $part->name,
            'Vehículos activos que la tienen' => fn (Part $part) => $part->vehicles_count,
            'Kilometraje promedio' => fn (Part $part) => $part->average_interval_km !== null ? (int) round((float) $part->average_interval_km) : null,
            'Kilometraje mínimo' => fn (Part $part) => $part->min_interval_km,
            'Kilometraje máximo' => fn (Part $part) => $part->max_interval_km,
        ];
    }
}
