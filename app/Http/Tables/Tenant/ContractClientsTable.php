<?php

namespace App\Http\Tables\Tenant;

use App\Actions\Contracts\ContractOwner;
use App\Http\Tables\DataTable;
use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Listado de contratos agrupado por cliente, como el legacy: cuántos contratos tiene, cuándo
 * vence el último y si tiene uno vigente. Filtro por estado (con o sin contrato vigente).
 *
 * @extends DataTable<Client>
 */
class ContractClientsTable extends DataTable
{
    public function __construct(Request $request, private ContractOwner $owner)
    {
        parent::__construct($request);
    }

    public function title(): string
    {
        return $this->owner->kind->label();
    }

    protected function query(): Builder
    {
        $search = $this->search();
        $owned = fn ($query) => $this->owner->contracts($query);
        $today = today()->toDateString();

        return Client::query()
            ->whereHas('contracts', $owned)
            ->withCount(['contracts' => $owned])
            ->withMax(['contracts' => $owned], 'ends_on')
            ->withExists(['contracts as has_current_contract' => fn ($query) => $owned($query)->whereDate('starts_on', '<=', $today)->whereDate('ends_on', '>=', $today)])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('identification', "%{$search}%")
                ->orWhereLike('first_names', "%{$search}%")
                ->orWhereLike('last_names', "%{$search}%")
                ->orWhereLike('business_name', "%{$search}%")
                ->orWhereLike('trade_name', "%{$search}%")))
            ->when($this->status() === 'current', fn ($query) => $query->whereHas('contracts', fn ($query) => $owned($query)->whereDate('starts_on', '<=', $today)->whereDate('ends_on', '>=', $today)))
            ->when($this->status() === 'none', fn ($query) => $query->whereDoesntHave('contracts', fn ($query) => $owned($query)->whereDate('starts_on', '<=', $today)->whereDate('ends_on', '>=', $today)));
    }

    protected function sortable(): array
    {
        return [
            'name' => DB::raw("coalesce(nullif(business_name, ''), nullif(trade_name, ''), first_names || ' ' || last_names)"),
            'contracts_count' => 'contracts_count',
            'last_ends_on' => 'contracts_max_ends_on',
        ];
    }

    protected function defaultSort(): array
    {
        return ['last_ends_on', 'desc'];
    }

    /**
     * @param  Client  $model
     */
    protected function toRow(Model $model): array
    {
        return [
            'id' => $model->id,
            'name' => $model->displayName(),
            'type_label' => $model->type->label(),
            'identification' => $model->identification,
            'phone' => $model->phone,
            'contracts_count' => $model->contracts_count,
            'last_ends_on' => $model->contracts_max_ends_on ? Carbon::parse($model->contracts_max_ends_on)->toDateString() : null,
            'has_current_contract' => (bool) $model->has_current_contract,
            'url' => route("{$this->owner->routePrefix()}.client", $model),
        ];
    }

    protected function exportColumns(): array
    {
        return [
            'Cliente' => fn (Client $client) => $client->displayName(),
            'Identificación' => fn (Client $client) => $client->identification,
            'Teléfono' => fn (Client $client) => $client->phone,
            'Contratos' => fn (Client $client) => $client->contracts_count,
            'Vence el último' => fn (Client $client) => $client->contracts_max_ends_on ? Carbon::parse($client->contracts_max_ends_on)->toDateString() : null,
            'Estado' => fn (Client $client) => $client->has_current_contract ? 'Con contrato vigente' : 'Sin contrato vigente',
        ];
    }

    protected function filters(): array
    {
        return ['status' => $this->status()];
    }

    protected function filterSummary(): array
    {
        $ally = $this->owner->ally();
        $status = match ($this->status()) {
            'current' => ['Con contrato vigente'],
            'none' => ['Sin contrato vigente'],
            default => [],
        };

        return [...($ally ? ["Aliado: {$ally->name}"] : []), ...parent::filterSummary(), ...$status];
    }

    /**
     * @return 'current'|'none'|null
     */
    private function status(): ?string
    {
        $status = $this->request->string('status')->value();

        return in_array($status, ['current', 'none'], true) ? $status : null;
    }
}
