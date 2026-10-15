<?php

namespace App\Http\Tables\Central;

use App\Enums\MigrationIssueSeverity;
use App\Enums\MigrationModule;
use App\Http\Tables\DataTable;
use App\Models\MigrationIssue;
use App\Models\MigrationRun;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Cuarentena, errores y advertencias de una corrida: el reporte que se revisa (y descarga) antes
 * de una corrida real. Búsqueda por detalle o id legacy (también entre los registros afectados de
 * una advertencia de resumen) y filtro por tipo.
 *
 * @extends DataTable<MigrationIssue>
 */
class MigrationIssuesTable extends DataTable
{
    public function __construct(Request $request, private MigrationRun $run)
    {
        parent::__construct($request);
    }

    public function title(): string
    {
        return "Corrida {$this->run->id} - cuarentena y errores";
    }

    protected function query(): Builder
    {
        $search = $this->search();
        $severity = $this->severity();

        return $this->run->issues()
            ->getQuery()
            ->with('step:id,module')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('message', "%{$search}%")
                ->orWhere('legacy_id', $search)
                ->when(ctype_digit($search), fn ($query) => $query
                    ->orWhereJsonContains('payload->legacy_ids', (int) $search)
                    ->orWhereJsonContains('payload->legacy_ids', $search))))
            ->when($severity, fn ($query) => $query->where('severity', $severity));
    }

    protected function sortable(): array
    {
        return ['id' => 'id', 'severity' => 'severity', 'legacy_id' => 'legacy_id'];
    }

    protected function defaultSort(): array
    {
        return ['id', 'asc'];
    }

    /**
     * @param  MigrationIssue  $model
     */
    protected function toRow(Model $model): array
    {
        return [
            'id' => $model->id,
            'severity' => $model->severity,
            'severity_label' => $model->severity->label(),
            'module' => $this->moduleLabel($model),
            'legacy_table' => $model->legacy_table,
            'legacy_id' => $model->legacy_id,
            'affected_ids' => $this->affectedIds($model),
            'message' => $model->message,
            'resolved' => $model->resolved_at !== null,
        ];
    }

    protected function exportColumns(): array
    {
        return [
            'Tipo' => fn (MigrationIssue $issue) => $issue->severity->label(),
            'Paso' => fn (MigrationIssue $issue) => $this->moduleLabel($issue),
            'Tabla legacy' => fn (MigrationIssue $issue) => $issue->legacy_table,
            'ID legacy' => fn (MigrationIssue $issue) => $issue->legacy_id ?? (implode(', ', $this->affectedIds($issue)) ?: null),
            'Detalle' => fn (MigrationIssue $issue) => $issue->message,
        ];
    }

    protected function filters(): array
    {
        return ['severity' => $this->severity()?->value];
    }

    protected function filterSummary(): array
    {
        $severity = $this->severity();

        return [...parent::filterSummary(), ...($severity ? ["Tipo: {$severity->label()}"] : [])];
    }

    /**
     * Registros legacy de una advertencia de resumen ("N registros de la tabla X: …").
     *
     * @return list<string>
     */
    private function affectedIds(MigrationIssue $issue): array
    {
        return array_map(strval(...), array_values((array) ($issue->payload['legacy_ids'] ?? [])));
    }

    private function moduleLabel(MigrationIssue $issue): ?string
    {
        return $issue->step ? (MigrationModule::tryFrom($issue->step->module)?->label() ?? $issue->step->module) : null;
    }

    private function severity(): ?MigrationIssueSeverity
    {
        return MigrationIssueSeverity::tryFrom($this->request->string('severity')->value());
    }
}
