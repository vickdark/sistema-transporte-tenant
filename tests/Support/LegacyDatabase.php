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
            $table->string('direccion')->default('Calle 1 # 2-3, Cartagena');
            $table->string('telefono')->default('3001234567');
            $table->string('correo')->default('contacto@transportes.test');
            $table->string('tipo_servicio')->default('Transporte especial de pasajeros');
            $table->string('codigo_territorial')->default('213');
            $table->string('resolucion_habilitacion')->default('114');
            $table->string('anio_habilitacion')->default('16');
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
        foreach (['aliados' => 'proveedor_id', 'proveedores' => 'aliado_id'] as $name => $twinColumn) {
            $schema->create($name, function (Blueprint $table) use ($twinColumn) {
                $table->increments('id');
                $table->string('nombre');
                $table->string('nit')->nullable();
                $table->string('telefono')->nullable();
                $table->string('direccion')->nullable();
                $table->string('email')->nullable();
                $table->integer($twinColumn)->nullable();
            });
        }
        $schema->create('clientes', function (Blueprint $table) {
            $table->increments('ID');
            $table->string('Tipo')->default('Persona');
            $table->string('TipoIdentificacion')->default('Cédula de Ciudadanía');
            $table->string('Identificacion')->default('1043652341');
            $table->string('DV')->nullable();
            $table->string('CodigoSucursal')->nullable();
            $table->string('RazonSocial')->nullable();
            $table->string('NombreComercial')->nullable();
            $table->string('Nombres')->nullable();
            $table->string('Apellidos')->nullable();
            $table->string('Ciudad')->default('Cartagena');
            $table->string('Direccion')->default('Calle 1 # 2-3');
            $table->string('Telefono')->default('3001234567');
            $table->string('Extension')->nullable();
            $table->string('estado')->default('Activo');
            $table->integer('aliadoid')->nullable();
        });

        $schema->create('rutas', function (Blueprint $table) {
            $table->increments('ID');
            $table->string('PuntoInicio')->nullable();
            $table->string('PuntoFin')->nullable();
            $table->text('Descripcion')->nullable();
            $table->string('TipoRuta')->default('urbana');
            $table->string('Estado')->default('Activa');
            $table->dateTime('FechaCreacion')->nullable();
        });

        foreach (['contratos_administracion', 'contratos_aliados'] as $name) {
            $schema->create($name, function (Blueprint $table) {
                $table->increments('id');
                $table->integer('cliente_id');
                $table->string('objeto_contrato')->default('CONTRATO PARA TRANSPORTE EMPRESARIAL');
                $table->date('fecha_inicio')->default('2026-01-01');
                $table->date('fecha_fin')->default('2026-12-31');
                $table->string('nit')->nullable();
                $table->string('razon_social')->nullable();
                $table->string('correo_empresa')->nullable();
                $table->string('telefono_empresa')->nullable();
            });
        }

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
