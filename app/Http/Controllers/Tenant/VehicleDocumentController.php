<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Vehicles\SaveVehicleDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\VehicleDocumentRequest;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Documentos de un vehículo. Cada tipo tiene un documento vigente:
 *
 * - Guardar: lo registra o lo corrige (sin dejar rastro; un archivo nuevo reemplaza al anterior).
 * - Renovar: el vigente pasa al historial con su archivo y se registra el nuevo.
 */
class VehicleDocumentController extends Controller
{
    public function store(VehicleDocumentRequest $request, Vehicle $vehicle, string $type, SaveVehicleDocument $documents): RedirectResponse
    {
        Gate::authorize('update', $vehicle);

        $documents->save($vehicle, $request->documentType(), $request->documentData(), $request->file('file'));

        return back()->with('success', "{$request->documentType()->label()} guardado.");
    }

    /**
     * @throws ValidationException
     */
    public function renew(VehicleDocumentRequest $request, Vehicle $vehicle, string $type): RedirectResponse
    {
        Gate::authorize('update', $vehicle);

        $current = $request->currentDocument()
            ?? throw ValidationException::withMessages(['expires_on' => 'Este documento todavía no está registrado: regístralo en lugar de renovarlo.']);

        $path = $request->file('file')?->store("vehicles/{$vehicle->id}/documents", Vehicle::FILES_DISK);

        DB::transaction(function () use ($request, $vehicle, $current, $path): void {
            $current->forceFill(['replaced_at' => now()])->save();

            $renewal = new VehicleDocument(['type' => $request->documentType(), ...$request->documentData()]);
            $renewal->vehicle()->associate($vehicle);
            $renewal->file_path = $path;
            $renewal->save();
        });

        return back()->with('success', "{$request->documentType()->label()} renovado: el anterior quedó en el historial.");
    }

    public function file(Vehicle $vehicle, VehicleDocument $document): StreamedResponse
    {
        Gate::authorize('view', $vehicle);

        $disk = Storage::disk(Vehicle::FILES_DISK);
        abort_unless($document->file_path && $disk->exists($document->file_path), 404);

        $name = Str::slug("{$document->type->label()} {$vehicle->plate}").'.'.pathinfo($document->file_path, PATHINFO_EXTENSION);

        return $disk->response($document->file_path, $name, ['Cache-Control' => 'private, max-age=3600']);
    }
}
