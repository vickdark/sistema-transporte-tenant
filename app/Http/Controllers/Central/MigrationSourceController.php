<?php

namespace App\Http\Controllers\Central;

use App\Actions\Migrations\LegacyFilesConnection;
use App\Enums\MigrationSourceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\StoreMigrationSourceRequest;
use App\Http\Requests\Central\TestLegacyFilesConnectionRequest;
use App\Http\Requests\Central\UpdateMigrationSourceRequest;
use App\Models\MigrationSource;
use Illuminate\Http\JsonResponse;
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
            'files_connection' => $request->filesConnection(),
            'notes' => $request->validated('notes'),
            'created_by' => $request->user('central')->id,
        ]);

        return to_route('central.migrations.index')->with('success', 'Fuente registrada.');
    }

    /**
     * Test the SFTP access to the legacy files without saving it. The request already connected
     * and checked `uploads/` (a failure answers 422 with the reason); this lists what it found.
     */
    public function testFiles(TestLegacyFilesConnectionRequest $request, LegacyFilesConnection $files): JsonResponse
    {
        $folders = collect($files->open($request->filesConnection())->directories('uploads'))
            ->map(fn (string $path): string => basename($path))
            ->sort()
            ->values();

        return response()->json([
            'message' => "Conexión correcta. uploads/ tiene {$folders->count()} carpetas: {$folders->implode(', ')}.",
            'folders' => $folders,
        ]);
    }

    /**
     * Show the form to edit a source's name, files access and notes (the database connection is
     * fixed). The SFTP password and private key are never sent: only whether they are set.
     */
    public function edit(MigrationSource $source): Response
    {
        $files = $source->files_connection;

        return Inertia::render('central/migrations/sources/Edit', [
            'source' => [
                ...$source->only(['id', 'name', 'notes']),
                'host' => $source->credentials['host'] ?? null,
                'database' => $source->credentials['database'] ?? null,
                'files' => $files ? [
                    'host' => $files['host'],
                    'port' => $files['port'],
                    'username' => $files['username'],
                    'root' => $files['root'],
                    'has_password' => filled($files['password']),
                    'has_private_key' => filled($files['private_key']),
                ] : null,
            ],
        ]);
    }

    public function update(UpdateMigrationSourceRequest $request, MigrationSource $source): RedirectResponse
    {
        $source->update([
            'name' => $request->validated('name'),
            'notes' => $request->validated('notes'),
            'files_connection' => $request->filesConnection(),
        ]);

        return to_route('central.migrations.index')->with('success', 'Fuente actualizada.');
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
