<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehiclePhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fotos de un vehículo: hasta cinco, en JPG, PNG o WEBP como en el legacy.
 */
class VehiclePhotoController extends Controller
{
    public const MAX_FILE_KB = 5120;

    public function store(Request $request, Vehicle $vehicle): RedirectResponse
    {
        Gate::authorize('update', $vehicle);

        $available = VehiclePhoto::MAX_PER_VEHICLE - $vehicle->photos()->count();

        $request->validate(
            [
                'photos' => ['required', 'array', 'min:1', 'max:'.max($available, 0)],
                'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_FILE_KB],
            ],
            [
                'photos.max' => $available > 0
                    ? "Solo puedes agregar {$available} foto(s) más: el máximo es ".VehiclePhoto::MAX_PER_VEHICLE.' por vehículo.'
                    : 'El vehículo ya tiene '.VehiclePhoto::MAX_PER_VEHICLE.' fotos: elimina alguna para agregar otra.',
                'photos.*.max' => 'Cada foto puede pesar máximo 5 MB.',
            ],
            ['photos' => 'fotos', 'photos.*' => 'foto'],
        );

        foreach ($request->file('photos') as $file) {
            $photo = new VehiclePhoto(['path' => $file->store("vehicles/{$vehicle->id}/photos", Vehicle::FILES_DISK)]);
            $photo->vehicle()->associate($vehicle)->save();
        }

        return back()->with('success', 'Fotos agregadas.');
    }

    public function show(Vehicle $vehicle, VehiclePhoto $photo): StreamedResponse
    {
        Gate::authorize('view', $vehicle);

        $disk = Storage::disk(Vehicle::FILES_DISK);
        abort_unless($disk->exists($photo->path), 404);

        return $disk->response($photo->path, headers: ['Cache-Control' => 'private, max-age=86400']);
    }

    public function destroy(Vehicle $vehicle, VehiclePhoto $photo): RedirectResponse
    {
        Gate::authorize('update', $vehicle);

        $photo->delete();
        Storage::disk(Vehicle::FILES_DISK)->delete($photo->path);

        return back()->with('success', 'Foto eliminada.');
    }
}
