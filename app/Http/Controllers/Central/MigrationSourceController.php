<?php

namespace App\Http\Controllers\Central;

use App\Enums\MigrationSourceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\StoreMigrationSourceRequest;
use App\Models\MigrationSource;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MigrationSourceController extends Controller
{
    /**
     * Show the form to register a legacy source.
     */
    public function create(): Response
    {
        return Inertia::render('central/migrations/sources/Create');
    }

    /**
     * Store a legacy source; its credentials are encrypted at rest.
     */
    public function store(StoreMigrationSourceRequest $request): RedirectResponse
    {
        MigrationSource::create([
            'name' => $request->validated('name'),
            'type' => MigrationSourceType::MysqlConnection,
            'credentials' => $request->credentials(),
            'notes' => $request->validated('notes'),
            'created_by' => $request->user('central')->id,
        ]);

        return to_route('central.migrations.index')->with('success', 'Fuente registrada.');
    }

    /**
     * Delete a legacy source that has never been used by a run.
     */
    public function destroy(MigrationSource $source): RedirectResponse
    {
        if ($source->runs()->exists()) {
            return back()->withErrors(['source' => 'No se puede eliminar una fuente que ya tiene corridas.']);
        }

        $source->delete();

        return back()->with('success', 'Fuente eliminada.');
    }
}
