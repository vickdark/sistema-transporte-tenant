<?php

namespace App\Http\Tables\Tenant;

use App\Enums\ClientType;
use App\Http\Tables\DataTable;
use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Listado de clientes del dueño del usuario (su aliado o la empresa): activos o inactivos
 * (en el legacy eran dos pantallas), filtro por tipo y búsqueda por identificación, nombre,
 * razón social, nombre comercial, teléfono o ciudad.
 *
 * @extends DataTable<Client>
 */
class ClientsTable extends DataTable
{
    public function title(): string
    {
        return $this->showsInactive() ? 'Clientes inactivos' : 'Clientes';
    }

    protected function query(): Builder
    {
        $search = $this->search();
        $type = $this->type();

        return Client::query()
            ->visibleTo($this->request->user())
            ->where('is_active', ! $this->showsInactive())
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('identification', "%{$search}%")
                ->orWhereLike('first_names', "%{$search}%")
                ->orWhereLike('last_names', "%{$search}%")
                ->orWhereLike('business_name', "%{$search}%")
                ->orWhereLike('trade_name', "%{$search}%")
                ->orWhereLike('phone', "%{$search}%")
                ->orWhereLike('city', "%{$search}%")
                ->orWhereLike('email', "%{$search}%")))
            ->when($type, fn ($query) => $query->where('type', $type));
    }

    protected function sortable(): array
    {
        return [
            'name' => DB::raw("coalesce(nullif(business_name, ''), nullif(trade_name, ''), first_names || ' ' || last_names)"),
            'identification' => 'identification',
            'city' => 'city',
            'created_at' => 'created_at',
        ];
    }

    protected function defaultSort(): array
    {
        // El legacy lista los más recientes primero.
        return ['created_at', 'desc'];
    }

    /**
     * @param  Client  $model
     */
    protected function toRow(Model $model): array
    {
        $viewer = $this->request->user();

        return [
            'id' => $model->id,
            'type' => $model->type,
            'type_label' => $model->type->label(),
            'identification_type_label' => $model->identification_type->label(),
            'identification' => $model->identification,
            'check_digit' => $model->check_digit,
            'branch_code' => $model->branch_code,
            'name' => $model->displayName(),
            'phone' => $model->phone,
            'city' => $model->city,
            'address' => $model->address,
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
            'Tipo' => fn (Client $client) => $client->type->label(),
            'Tipo de identificación' => fn (Client $client) => $client->identification_type->label(),
            'Identificación' => fn (Client $client) => $client->identification,
            'DV' => fn (Client $client) => $client->check_digit,
            'Sucursal' => fn (Client $client) => $client->branch_code,
            'Nombre / razón social' => fn (Client $client) => $client->displayName(),
            'Nombre comercial' => fn (Client $client) => $client->trade_name,
            'Ciudad' => fn (Client $client) => $client->city,
            'Dirección' => fn (Client $client) => $client->address,
            'Teléfono' => fn (Client $client) => $client->phone,
            'Extensión' => fn (Client $client) => $client->extension,
            'Correo' => fn (Client $client) => $client->email,
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

    private function type(): ?ClientType
    {
        return ClientType::tryFrom($this->request->string('type')->value());
    }
}
