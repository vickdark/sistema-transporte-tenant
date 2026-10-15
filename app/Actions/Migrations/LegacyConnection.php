<?php

namespace App\Actions\Migrations;

use App\Models\MigrationSource;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Abre la conexión a una fuente legacy con sus credenciales cifradas. La sesión se pone en solo
 * lectura: aunque el usuario de MySQL tuviera más permisos, el sistema nunca escribe en la fuente.
 */
class LegacyConnection
{
    public function connect(MigrationSource $source): ConnectionInterface
    {
        $name = 'legacy_source_'.$source->id;
        $credentials = $source->credentials ?? [];

        config(["database.connections.{$name}" => [
            'driver' => 'mysql',
            'host' => $credentials['host'] ?? null,
            'port' => $credentials['port'] ?? 3306,
            'database' => $credentials['database'] ?? null,
            'username' => $credentials['username'] ?? null,
            'password' => $credentials['password'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
            'prefix' => '',
            'strict' => true,
        ]]);

        DB::purge($name);

        $connection = DB::connection($name);
        $connection->statement('SET SESSION TRANSACTION READ ONLY');

        return $connection;
    }

    public function disconnect(ConnectionInterface $connection): void
    {
        DB::purge($connection->getName());
    }
}
