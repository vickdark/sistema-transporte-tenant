<?php

namespace Tests\Support;

use App\Actions\Migrations\LegacyFilesConnection;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Servidor de archivos legacy de prueba: en lugar de conectarse por SFTP, la carpeta de la fuente
 * (`/var/www/legacy`) se abre dentro de una carpeta temporal. El servidor `sin-acceso.test` intenta
 * una conexión SFTP real a un puerto cerrado de esta máquina, que la rechaza.
 */
class LegacyFilesServer
{
    public const UNREACHABLE_HOST = 'sin-acceso.test';

    /**
     * Crea el servidor de prueba y devuelve su carpeta temporal (lo que sería `/` en el servidor).
     */
    public static function fake(): string
    {
        $server = sys_get_temp_dir().DIRECTORY_SEPARATOR.'legacy-server-'.uniqid();
        File::ensureDirectoryExists($server);

        app()->instance(LegacyFilesConnection::class, new class($server) extends LegacyFilesConnection
        {
            public function __construct(private readonly string $server) {}

            public function open(array $connection): FilesystemAdapter
            {
                if ($connection['host'] === LegacyFilesServer::UNREACHABLE_HOST) {
                    return parent::open([...$connection, 'host' => '127.0.0.1', 'port' => 1]);
                }

                /** @var FilesystemAdapter $disk */
                $disk = Storage::build(['driver' => 'local', 'root' => $this->server.$connection['root'], 'throw' => true]);

                return $disk;
            }
        });

        return $server;
    }

    /**
     * @return array{host: string, port: int, username: string, password: string, private_key: null, root: string}
     */
    public static function connection(string $root = '/var/www/legacy'): array
    {
        return ['host' => 'legacy.test', 'port' => 22, 'username' => 'lector', 'password' => 'secreto-sftp', 'private_key' => null, 'root' => $root];
    }
}
