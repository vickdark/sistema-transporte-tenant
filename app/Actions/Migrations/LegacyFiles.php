<?php

namespace App\Actions\Migrations;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * Archivos del legacy (documentos, fotos…) en la carpeta de su servidor que contiene `uploads/`,
 * abierta por SFTP (LegacyFilesConnection). Las rutas que guarda la base legacy
 * (`uploads/vehiculos/ABC123/foto.jpg`) se resuelven tal cual contra ella y nunca fuera de ella.
 * Solo se lee; en una simulación no se copia nada.
 */
class LegacyFiles
{
    public function __construct(
        private readonly ?FilesystemAdapter $legacy,
        private readonly bool $dryRun,
    ) {}

    public function configured(): bool
    {
        return $this->legacy !== null;
    }

    /**
     * Ruta del archivo dentro de la carpeta del legacy, o null si no está (o la ruta se sale de ella).
     */
    public function locate(?string $legacyPath): ?string
    {
        $relative = ltrim(str_replace('\\', '/', trim((string) $legacyPath)), '/');

        if ($this->legacy === null || $relative === '' || in_array('..', explode('/', $relative), true)) {
            return null;
        }

        return $this->legacy->fileExists($relative) ? $relative : null;
    }

    /**
     * Copia el archivo al disco de la empresa y verifica que llegó completo (mismo tamaño que en
     * el servidor legacy y mismo checksum que lo descargado). En una simulación no copia nada.
     */
    public function copy(string $legacyPath, string $disk, string $destination): bool
    {
        if ($this->dryRun) {
            return true;
        }

        $contents = (string) $this->legacy->get($legacyPath);

        if (strlen($contents) !== $this->legacy->size($legacyPath)) {
            return false;
        }

        $storage = Storage::disk($disk);
        $storage->put($destination, $contents);

        if (hash('sha256', (string) $storage->get($destination)) !== hash('sha256', $contents)) {
            $storage->delete($destination);

            return false;
        }

        return true;
    }
}
