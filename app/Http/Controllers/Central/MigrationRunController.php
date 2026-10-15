<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\MigrationIssue;
use App\Models\MigrationRun;
use App\Models\MigrationRunStep;
use Inertia\Inertia;
use Inertia\Response;

class MigrationRunController extends Controller
{
    /**
     * Show a run with its steps and its most recent issues.
     */
    public function show(MigrationRun $run): Response
    {
        $run->load(['tenant:id,name', 'source:id,name', 'starter:id,name']);

        return Inertia::render('central/migrations/runs/Show', [
            'run' => [
                'id' => $run->id,
                'tenant' => $run->tenant->name,
                'source' => $run->source->name,
                'started_by' => $run->starter?->name,
                'mode_label' => $run->mode->label(),
                'status' => $run->status,
                'status_label' => $run->status->label(),
                'modules' => $run->modules,
                'started_at' => $run->started_at?->toDateTimeString(),
                'finished_at' => $run->finished_at?->toDateTimeString(),
            ],
            'steps' => $run->steps()
                ->orderBy('id')
                ->get()
                ->map(fn (MigrationRunStep $step): array => [
                    'id' => $step->id,
                    'module' => $step->module,
                    'status' => $step->status,
                    'status_label' => $step->status->label(),
                    'read_count' => $step->read_count,
                    'inserted_count' => $step->inserted_count,
                    'updated_count' => $step->updated_count,
                    'quarantined_count' => $step->quarantined_count,
                    'skipped_count' => $step->skipped_count,
                    'error' => $step->error,
                ]),
            'issues' => $run->issues()
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (MigrationIssue $issue): array => [
                    'id' => $issue->id,
                    'severity' => $issue->severity,
                    'severity_label' => $issue->severity->label(),
                    'legacy_table' => $issue->legacy_table,
                    'legacy_id' => $issue->legacy_id,
                    'message' => $issue->message,
                    'resolved' => $issue->resolved_at !== null,
                ]),
            'issuesCount' => $run->issues()->count(),
        ]);
    }
}
