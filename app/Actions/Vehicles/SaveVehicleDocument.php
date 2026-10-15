<?php

namespace App\Actions\Vehicles;

use App\Enums\VehicleDocumentType;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Registra o corrige el documento vigente de un tipo (sin dejar historial; para eso se renueva).
 * Un archivo nuevo reemplaza al anterior, que se borra; sin archivo nuevo se conserva el que había.
 */
class SaveVehicleDocument
{
    /**
     * @param  array{number: string|null, issued_on: string|null, expires_on: string|null}  $data
     */
    public function save(Vehicle $vehicle, VehicleDocumentType $type, array $data, ?UploadedFile $file): VehicleDocument
    {
        $document = $vehicle->currentDocuments()->where('type', $type)->first() ?? new VehicleDocument(['type' => $type]);
        $previousFile = $document->file_path;

        $document->fill($data);
        $document->vehicle()->associate($vehicle);

        if ($file !== null) {
            $document->file_path = $file->store("vehicles/{$vehicle->id}/documents", Vehicle::FILES_DISK);
        }

        $document->save();

        if ($file !== null && $previousFile) {
            Storage::disk(Vehicle::FILES_DISK)->delete($previousFile);
        }

        return $document;
    }
}
