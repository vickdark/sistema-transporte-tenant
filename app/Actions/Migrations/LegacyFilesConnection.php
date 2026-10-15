<?php

namespace App\Actions\Migrations;

use App\Models\MigrationSource;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Abre por SFTP la carpeta del legacy en su servidor (la que contiene `uploads/`), con las
 * credenciales cifradas de la fuente. Así la migración lee los archivos igual desde el propio
 * servidor que desde otro equipo. El sistema solo lee de ella: nunca escribe ni borra.
 */
class LegacyFilesConnection
{
    /**
     * Carpeta del legacy de la fuente, ya verificada, o null si la fuente no tiene acceso a archivos.
     *
     * @throws RuntimeException si no se puede conectar o la carpeta no contiene `uploads/`
     */
    public function connect(MigrationSource $source): ?FilesystemAdapter
    {
        $connection = $source->files_connection;

        if ($connection === null) {
            return null;
        }

        $disk = $this->open($connection);

        if (! $this->hasUploads($disk, $connection)) {
            throw new RuntimeException("En el servidor no existe {$connection['root']}/uploads: escribe la carpeta del legacy que contiene uploads/.");
        }

        return $disk;
    }

    /**
     * Prueba la conexión y dice si la carpeta contiene `uploads/`.
     *
     * @param  array{host: string, port: int, username: string, password: string|null, private_key: string|null, root: string}  $connection
     *
     * @throws RuntimeException con el motivo real si no se puede conectar
     */
    public function hasUploads(FilesystemAdapter $disk, array $connection): bool
    {
        try {
            return $disk->directoryExists('uploads');
        } catch (Throwable $exception) {
            // Flysystem envuelve el error real (conexión rechazada, credenciales…) en uno genérico.
            while ($exception->getPrevious() !== null) {
                $exception = $exception->getPrevious();
            }

            throw new RuntimeException("No se pudo conectar por SFTP a {$connection['host']}: {$exception->getMessage()}", previous: $exception);
        }
    }

    /**
     * @param  array{host: string, port: int, username: string, password: string|null, private_key: string|null, root: string}  $connection
     */
    public function open(array $connection): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::build(array_filter([
            'driver' => 'sftp',
            'host' => $connection['host'],
            'port' => $connection['port'],
            'username' => $connection['username'],
            'password' => $connection['password'] ?: null,
            'privateKey' => $connection['private_key'] ?: null,
            'root' => $connection['root'],
            'timeout' => 30,
            'throw' => true,
        ], fn (mixed $value): bool => $value !== null));

        return $disk;
    }

    /**
     * Ruta de la carpeta del legacy sin espacios ni barra final. Acepta también la carpeta
     * `uploads/` misma: en ese caso queda la que la contiene.
     */
    public static function normalizeRoot(?string $root): ?string
    {
        if ($root === null || trim($root) === '') {
            return $root;
        }

        $path = rtrim(trim($root), '/\\');

        return preg_match('#[/\\\\]uploads$#i', $path) === 1 ? substr($path, 0, -strlen('/uploads')) : $path;
    }
}
