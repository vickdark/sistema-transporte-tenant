<?php

namespace App\Http\Controllers\Central;

use App\Actions\Migrations\StartMigrationRun;
use App\Enums\MigrationIssueSeverity;
use App\Enums\MigrationModule;
use App\Enums\MigrationRunMode;
use App\Enums\MigrationRunStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\StoreMigrationRunRequest;
use App\Http\Tables\Central\MigrationIssuesTable;
use App\Http\Tables\Central\MigrationRunsTable;
use App\Models\MigrationRun;
use App\Models\MigrationRunStep;
use App\Models\MigrationSource;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class MigrationRunController extends Controller
{
    /**
     * Show the form to start a run.
     */
    public function create(): Response
    {
        return Inertia::render('central/migrations/runs/Create', [
            'sources' => MigrationSource::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (MigrationSource $source): array => ['value' => $source->id, 'label' => $source->name]),
            'tenants' => Tenant::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Tenant $tenant): array => ['value' => $tenant->id, 'label' => $tenant->name]),
            'modules' => array_map(fn (MigrationModule $module): array => [
                'value' => $module->value,
                'label' => $module->label(),
                'depends_on' => array_map(fn (MigrationModule $dependency): string => $dependency->value, $module->dependsOn()),
            ], MigrationModule::cases()),
            'modes' => array_map(fn (MigrationRunMode $mode): array => [
                'value' => $mode->value,
                'label' => $mode->label(),
            ], MigrationRunMode::cases()),
        ]);
    }

    /**
     * Start a run in the queue.
     */
    public function store(StoreMigrationRunRequest $request, StartMigrationRun $startMigrationRun): RedirectResponse
    {
        $run = $startMigrationRun->handle(
            MigrationSource::findOrFail($request->validated('migration_source_id')),
            Tenant::findOrFail($request->validated('tenant_id')),
            MigrationRunMode::from($request->validated('mode')),
            $request->modules(),
            $request->user('central'),
        );

        return to_route('central.migrations.runs.show', $run)->with('success', 'Corrida en cola.');
    }

    /**
     * Cancel a pending or running run; the job stops at the end of the current batch.
     */
    public function cancel(MigrationRun $run): RedirectResponse
    {
        if (! $run->isActive()) {
            return back()->withErrors(['run' => 'La corrida ya terminó.']);
        }

        $run->update([
            'status' => MigrationRunStatus::Cancelled,
            'finished_at' => $run->status === MigrationRunStatus::Pending ? now() : null,
        ]);

        return back()->with('success', 'Corrida cancelada.');
    }

    /**
     * Show a run with its steps and its issues (paginated in the server).
     */
    public function show(Request $request, MigrationRun $run): Response
    {
        $run->load(['tenant:id,name', 'source:id,name', 'starter:id,name']);
        $issues = new MigrationIssuesTable($request, $run);

        return Inertia::render('central/migrations/runs/Show', [
            'run' => [
                'id' => $run->id,
                'tenant' => $run->tenant->name,
                'source' => $run->source->name,
                'started_by' => $run->starter?->name,
                'mode_label' => $run->mode->label(),
                'status' => $run->status,
                'status_label' => $run->status->label(),
                'is_active' => $run->isActive(),
                'waiting_for_worker' => $run->isWaitingForWorker(),
                'modules' => array_map(fn (string $module): string => MigrationModule::tryFrom($module)?->label() ?? $module, $run->modules),
                'started_at' => $run->started_at?->toDateTimeString(),
                'finished_at' => $run->finished_at?->toDateTimeString(),
            ],
            'steps' => $run->steps()
                ->orderBy('id')
                ->get()
                ->map(fn (MigrationRunStep $step): array => [
                    'id' => $step->id,
                    'module' => MigrationModule::tryFrom($step->module)?->label() ?? $step->module,
                    'status' => $step->status,
                    'status_label' => $step->status->label(),
                    'read_count' => $step->read_count,
                    'inserted_count' => $step->inserted_count,
                    'updated_count' => $step->updated_count,
                    'quarantined_count' => $step->quarantined_count,
                    'skipped_count' => $step->skipped_count,
                    'total_count' => $step->total_count,
                    'error' => $step->error,
                    'started_at' => $step->started_at?->toIso8601String(),
                    'finished_at' => $step->finished_at?->toIso8601String(),
                ]),
            'issues' => $issues->rows(),
            'issuesQuery' => $issues->state(),
            'severities' => array_map(
                fn (MigrationIssueSeverity $severity): array => ['value' => $severity->value, 'label' => $severity->label()],
                MigrationIssueSeverity::cases(),
            ),
        ]);
    }

    /**
     * Export the runs list (same search, filters and order as the dashboard) to Excel or PDF.
     */
    public function export(Request $request, string $format): SymfonyResponse
    {
        return (new MigrationRunsTable($request))->export($format);
    }

    /**
     * Export the quarantine and errors report of a run to Excel or PDF.
     */
    public function exportIssues(Request $request, MigrationRun $run, string $format): SymfonyResponse
    {
        return (new MigrationIssuesTable($request, $run))->export($format);
    }
}
