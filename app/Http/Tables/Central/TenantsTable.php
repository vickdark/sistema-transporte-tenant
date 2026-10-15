<?php

namespace App\Http\Tables\Central;

use App\Enums\TenantStatus;
use App\Http\Tables\DataTable;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Listado de empresas (inquilinos): búsqueda por nombre o NIT y filtro por estado.
 *
 * @extends DataTable<Tenant>
 */
class TenantsTable extends DataTable
{
    public function title(): string
    {
        return 'Empresas';
    }

    protected function query(): Builder
    {
        $search = $this->search();
        $status = $this->status();

        return Tenant::query()
            ->with('subdomain')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('nit', "%{$search}%")))
            ->when($status, fn ($query) => $query->where('status', $status));
    }

    protected function sortable(): array
    {
        return ['name' => 'name', 'nit' => 'nit', 'status' => 'status', 'created_at' => 'created_at'];
    }

    protected function defaultSort(): array
    {
        return ['name', 'asc'];
    }

    /**
     * @param  Tenant  $model
     */
    protected function toRow(Model $model): array
    {
        return [
            'id' => $model->id,
            'name' => $model->name,
            'nit' => $model->nit,
            'status' => $model->status,
            'status_label' => $model->status->label(),
            'subdomain' => $model->subdomain?->domain,
            'created_at' => $model->created_at?->toDateString(),
        ];
    }

    protected function exportColumns(): array
    {
        return [
            'Empresa' => fn (Tenant $tenant) => $tenant->name,
            'NIT' => fn (Tenant $tenant) => $tenant->nit,
            'Subdominio' => fn (Tenant $tenant) => $tenant->subdomain?->domain,
            'Estado' => fn (Tenant $tenant) => $tenant->status->label(),
            'Correo' => fn (Tenant $tenant) => $tenant->email,
            'Teléfono' => fn (Tenant $tenant) => $tenant->phone,
            'Ciudad' => fn (Tenant $tenant) => $tenant->city,
            'Creada' => fn (Tenant $tenant) => $tenant->created_at?->toDateString(),
        ];
    }

    protected function filters(): array
    {
        return ['status' => $this->status()?->value];
    }

    protected function filterSummary(): array
    {
        $status = $this->status();

        return [...parent::filterSummary(), ...($status ? ["Estado: {$status->label()}"] : [])];
    }

    private function status(): ?TenantStatus
    {
        return TenantStatus::tryFrom($this->request->string('status')->value());
    }
}
