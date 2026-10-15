<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\MigrationRun;
use App\Models\MigrationSource;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tablero del módulo de migración de datos legacy: fuentes registradas y corridas.
 */
class MigrationsController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('central/migrations/Index', [
            'sources' => MigrationSource::query()
                ->withCount('runs')
                ->latest()
                ->get()
                ->map(fn (MigrationSource $source): array => [
                    'id' => $source->id,
                    'name' => $source->name,
                    'type_label' => $source->type->label(),
                    'host' => $source->credentials['host'] ?? null,
                    'database' => $source->credentials['database'] ?? null,
                    'runs_count' => $source->runs_count,
                    'created_at' => $source->created_at?->toDateString(),
                ]),
            'runs' => MigrationRun::query()
                ->with(['tenant:id,name', 'source:id,name'])
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (MigrationRun $run): array => [
                    'id' => $run->id,
                    'tenant' => $run->tenant->name,
                    'source' => $run->source->name,
                    'mode_label' => $run->mode->label(),
                    'status' => $run->status,
                    'status_label' => $run->status->label(),
                    'created_at' => $run->created_at?->toDateTimeString(),
                ]),
        ]);
    }
}
