<?php

namespace App\Http\Tables\Central;

use App\Enums\MigrationModule;
use App\Enums\MigrationRunStatus;
use App\Http\Tables\DataTable;
use App\Models\MigrationRun;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Corridas de migración: búsqueda por empresa o fuente y filtro por estado.
 *
 * @extends DataTable<MigrationRun>
 */
class MigrationRunsTable extends DataTable
{
    public function title(): string
    {
        return 'Corridas de migración';
    }

    protected function query(): Builder
    {
        $search = $this->search();
        $status = $this->status();

        return MigrationRun::query()
            ->with(['tenant:id,name', 'source:id,name', 'starter:id,name'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereHas('tenant', fn ($query) => $query->whereLike('name', "%{$search}%"))
                ->orWhereHas('source', fn ($query) => $query->whereLike('name', "%{$search}%"))))
            ->when($status, fn ($query) => $query->where('status', $status));
    }

    protected function sortable(): array
    {
        return ['id' => 'id', 'status' => 'status', 'created_at' => 'created_at'];
    }

    protected function defaultSort(): array
    {
        return ['id', 'desc'];
    }

    /**
     * @param  MigrationRun  $model
     */
    protected function toRow(Model $model): array
    {
        return [
            'id' => $model->id,
            'tenant' => $model->tenant->name,
            'source' => $model->source->name,
            'mode_label' => $model->mode->label(),
            'status' => $model->status,
            'status_label' => $model->status->label(),
            'created_at' => $model->created_at?->toDateTimeString(),
        ];
    }

    protected function exportColumns(): array
    {
        return [
            '#' => fn (MigrationRun $run) => $run->id,
            'Empresa' => fn (MigrationRun $run) => $run->tenant->name,
            'Fuente' => fn (MigrationRun $run) => $run->source->name,
            'Modo' => fn (MigrationRun $run) => $run->mode->label(),
            'Estado' => fn (MigrationRun $run) => $run->status->label(),
            'Módulos' => fn (MigrationRun $run) => collect($run->modules)
                ->map(fn (string $module): string => MigrationModule::tryFrom($module)?->label() ?? $module)
                ->implode(', '),
            'Iniciada por' => fn (MigrationRun $run) => $run->starter?->name,
            'Inicio' => fn (MigrationRun $run) => $run->started_at?->toDateTimeString(),
            'Fin' => fn (MigrationRun $run) => $run->finished_at?->toDateTimeString(),
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

    private function status(): ?MigrationRunStatus
    {
        return MigrationRunStatus::tryFrom($this->request->string('status')->value());
    }
}
