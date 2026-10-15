<?php

namespace App\Http\Tables\Tenant;

use App\Enums\VehicleClass;
use App\Enums\VehicleOwnerType;
use App\Http\Tables\DataTable;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Listado de vehículos que ve el usuario: activos o inactivos (en el legacy eran dos pantallas),
 * filtros por tipo de dueño y por clase, y búsqueda por placa, número interno, marca, línea o
 * nombre del dueño. Cada fila cuenta sus documentos vencidos y por vencer.
 *
 * @extends DataTable<Vehicle>
 */
class VehiclesTable extends DataTable
{
    public function title(): string
    {
        return $this->showsInactive() ? 'Vehículos inactivos' : 'Vehículos';
    }

    protected function query(): Builder
    {
        $search = $this->search();
        $ownerType = $this->ownerType();
        $class = $this->vehicleClass();

        return Vehicle::query()
            ->visibleTo($this->request->user())
            ->with(['supplier:id,name', 'ally:id,name'])
            ->withCount([
                'currentDocuments as expired_documents_count' => fn (Builder $query) => $query->expired(),
                'currentDocuments as expiring_documents_count' => fn (Builder $query) => $query->expiring(),
            ])
            ->where('is_active', ! $this->showsInactive())
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('plate', "%{$search}%")
                ->orWhereLike('internal_number', "%{$search}%")
                ->orWhereLike('brand', "%{$search}%")
                ->orWhereLike('line', "%{$search}%")
                ->orWhereHas('supplier', fn ($query) => $query->whereLike('name', "%{$search}%"))
                ->orWhereHas('ally', fn ($query) => $query->whereLike('name', "%{$search}%"))))
            ->when($ownerType, fn ($query) => $query->ownedBy($ownerType))
            ->when($class, fn ($query) => $query->where('vehicle_class', $class));
    }

    protected function sortable(): array
    {
        return [
            'plate' => 'plate',
            'model_year' => 'model_year',
            'current_mileage' => 'current_mileage',
            'created_at' => 'created_at',
        ];
    }

    protected function defaultSort(): array
    {
        // El legacy ordena por placa.
        return ['plate', 'asc'];
    }

    /**
     * @param  Vehicle  $model
     */
    protected function toRow(Model $model): array
    {
        $viewer = $this->request->user();

        return [
            'id' => $model->id,
            'plate' => $model->plate,
            'internal_number' => $model->internal_number,
            'brand' => $model->brand,
            'line' => $model->line,
            'model_year' => $model->model_year,
            'class_label' => $model->vehicle_class->label(),
            'color' => $model->color,
            'owner_type' => $model->ownerType(),
            'owner_type_label' => $model->ownerType()->label(),
            'owner_name' => $model->ownerName(),
            'current_mileage' => (float) $model->current_mileage,
            'expired_documents_count' => $model->expired_documents_count,
            'expiring_documents_count' => $model->expiring_documents_count,
            'is_active' => $model->is_active,
            'can' => [
                'update' => $viewer->can('update', $model),
                'toggle_active' => $viewer->can('toggleActive', $model),
            ],
        ];
    }

    protected function exportColumns(): array
    {
        return [
            'Placa' => fn (Vehicle $vehicle) => $vehicle->plate,
            'Número interno' => fn (Vehicle $vehicle) => $vehicle->internal_number,
            'Marca' => fn (Vehicle $vehicle) => $vehicle->brand,
            'Línea' => fn (Vehicle $vehicle) => $vehicle->line,
            'Modelo' => fn (Vehicle $vehicle) => $vehicle->model_year,
            'Clase' => fn (Vehicle $vehicle) => $vehicle->vehicle_class->label(),
            'Color' => fn (Vehicle $vehicle) => $vehicle->color,
            'Tipo de dueño' => fn (Vehicle $vehicle) => $vehicle->ownerType()->label(),
            'Dueño' => fn (Vehicle $vehicle) => $vehicle->ownerName(),
            'Kilometraje actual' => fn (Vehicle $vehicle) => (float) $vehicle->current_mileage,
            'Documentos vencidos' => fn (Vehicle $vehicle) => $vehicle->expired_documents_count,
            'Documentos por vencer' => fn (Vehicle $vehicle) => $vehicle->expiring_documents_count,
        ];
    }

    protected function filters(): array
    {
        return [
            'status' => $this->showsInactive() ? 'inactive' : 'active',
            'owner_type' => $this->ownerType()?->value,
            'class' => $this->vehicleClass()?->value,
        ];
    }

    protected function filterSummary(): array
    {
        $ownerType = $this->ownerType();
        $class = $this->vehicleClass();

        return [
            ...parent::filterSummary(),
            ...($ownerType ? ["Dueño: {$ownerType->label()}"] : []),
            ...($class ? ["Clase: {$class->label()}"] : []),
        ];
    }

    private function showsInactive(): bool
    {
        return $this->request->string('status')->value() === 'inactive';
    }

    private function ownerType(): ?VehicleOwnerType
    {
        return VehicleOwnerType::tryFrom($this->request->string('owner_type')->value());
    }

    private function vehicleClass(): ?VehicleClass
    {
        return VehicleClass::tryFrom($this->request->string('class')->value());
    }
}
