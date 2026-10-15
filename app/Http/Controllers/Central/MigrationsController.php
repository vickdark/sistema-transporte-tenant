<?php

namespace App\Http\Controllers\Central;

use App\Enums\MigrationRunStatus;
use App\Http\Controllers\Controller;
use App\Http\Tables\Central\MigrationRunsTable;
use App\Models\MigrationSource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tablero del módulo de migración de datos legacy: fuentes registradas y corridas.
 */
class MigrationsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $runs = new MigrationRunsTable($request);

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
            'runs' => $runs->rows(),
            'query' => $runs->state(),
            'statuses' => array_map(
                fn (MigrationRunStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                MigrationRunStatus::cases(),
            ),
        ]);
    }
}
