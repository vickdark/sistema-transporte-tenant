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
 * de una corrida real. Búsqueda por detalle o id legacy y filtro por tipo.
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
                ->orWhere('legacy_id', $search)))
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
            'ID legacy' => fn (MigrationIssue $issue) => $issue->legacy_id,
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

    private function moduleLabel(MigrationIssue $issue): ?string
    {
        return $issue->step ? (MigrationModule::tryFrom($issue->step->module)?->label() ?? $issue->step->module) : null;
    }

    private function severity(): ?MigrationIssueSeverity
    {
        return MigrationIssueSeverity::tryFrom($this->request->string('severity')->value());
    }
}
