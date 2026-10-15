<?php

namespace Tests\Support;

use App\Actions\Migrations\LegacyConnection;
use App\Models\MigrationSource;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/**
 * Fuente legacy de prueba: una base SQLite en memoria con las tablas de sisTransporte que leen
 * los importadores (solo las columnas que usan), en lugar de una conexión MySQL real.
 */
class LegacyDatabase
{
    public const CONNECTION = 'legacy_fixture';

    /**
     * Crea las tablas legacy vacías (más la empresa con el NIT dado) y hace que toda fuente
     * registrada se conecte a ellas.
     */
    public static function fake(string $nit): ConnectionInterface
    {
        config(['database.connections.'.self::CONNECTION => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge(self::CONNECTION);
        $legacy = DB::connection(self::CONNECTION);
        $schema = $legacy->getSchemaBuilder();

        $schema->create('configuracion_empresa', function (Blueprint $table) {
            $table->increments('id');
            $table->string('razon_social');
            $table->string('nit');
        });
        $schema->create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre');
            $table->text('descripcion')->nullable();
        });
        $schema->create('permisos', function (Blueprint $table) {
            $table->increments('ID');
            $table->string('nombre');
            $table->string('url')->unique();
        });
        $schema->create('rol_permisos', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('rol_id');
            $table->integer('permiso_id');
        });
        $schema->create('usuarios', function (Blueprint $table) {
            $table->increments('ID');
            $table->string('NombreUsuario');
            $table->string('Contrasena');
            $table->integer('EmpleadoID')->nullable();
            $table->integer('rol_id')->nullable();
            $table->integer('ClienteID')->nullable();
            $table->integer('ProveedorID')->nullable();
            $table->integer('AliadoID')->nullable();
        });
        $schema->create('empleados', function (Blueprint $table) {
            $table->increments('ID');
            $table->string('Nombre');
            $table->string('Correo');
        });
        foreach (['aliados', 'proveedores'] as $name) {
            $schema->create($name, function (Blueprint $table) {
                $table->increments('id');
                $table->string('nombre');
                $table->string('email')->nullable();
            });
        }
        $schema->create('clientes', function (Blueprint $table) {
            $table->increments('ID');
            $table->string('RazonSocial')->nullable();
            $table->string('Nombres')->nullable();
            $table->string('Apellidos')->nullable();
        });

        $legacy->table('configuracion_empresa')->insert(['razon_social' => 'Transportes de prueba SAS', 'nit' => $nit]);

        app()->instance(LegacyConnection::class, new class extends LegacyConnection
        {
            public function connect(MigrationSource $source): ConnectionInterface
            {
                return DB::connection(LegacyDatabase::CONNECTION);
            }

            public function disconnect(ConnectionInterface $connection): void
            {
                // La base en memoria se perdería: los tests la leen después de la corrida.
            }
        });

        return $legacy;
    }

    /**
     * Inserta un rol legacy con sus permisos (por URL; las URLs nuevas se agregan a `permisos`).
     *
     * @param  list<string>  $urls
     */
    public static function role(ConnectionInterface $legacy, int $id, string $name, array $urls = []): void
    {
        $legacy->table('roles')->insert(['id' => $id, 'nombre' => $name, 'descripcion' => "Rol {$name}"]);

        foreach ($urls as $url) {
            $permissionId = $legacy->table('permisos')->where('url', $url)->value('ID')
                ?? $legacy->table('permisos')->insertGetId(['nombre' => $url, 'url' => $url]);

            $legacy->table('rol_permisos')->insert(['rol_id' => $id, 'permiso_id' => $permissionId]);
        }
    }
}
