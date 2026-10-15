<?php

use App\Enums\BaseRole;
use App\Enums\ClientType;
use App\Enums\MigrationIssueSeverity;
use App\Enums\MigrationRunStatus;
use App\Enums\Permission;
use App\Enums\RouteStatus;
use App\Enums\RouteType;
use App\Models\Ally;
use App\Models\CentralUser;
use App\Models\Client;
use App\Models\CompanyProfile;
use App\Models\LegacyAlias;
use App\Models\MigrationRun;
use App\Models\MigrationSource;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TransportRoute;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Support\LegacyDatabase;

beforeEach(function () {
    $this->actingAs(CentralUser::factory()->create(), 'central');

    $this->tenant = Tenant::factory()->create(['nit' => '900980138']);
    $this->source = MigrationSource::factory()->create();
    $this->legacy = LegacyDatabase::fake(nit: '900980138-1');

    LegacyDatabase::role($this->legacy, 1, 'Administrador', ['/usuarios/', '/roles/']);
    LegacyDatabase::role($this->legacy, 2, 'Administrativo', ['/usuarios/index.php', '/usuarios/update.php', '/ordenServicio/index.php']);
    LegacyDatabase::role($this->legacy, 4, 'Creador', ['/usuarios/create.php', '/usuarios/']);
    LegacyDatabase::role($this->legacy, 8, 'Aliado', ['/proveedoresdash_aliados/index.php']);
});

/**
 * Inicia una corrida por el panel; con la cola `sync` de los tests se ejecuta en el acto.
 *
 * @param  list<string>  $modules
 */
function startLegacyRun(array $modules = ['roles', 'users'], string $mode = 'live'): MigrationRun
{
    test()->post(route('central.migrations.runs.store'), [
        'migration_source_id' => test()->source->id,
        'tenant_id' => test()->tenant->id,
        'mode' => $mode,
        'modules' => $modules,
    ])->assertSessionHasNoErrors();

    return MigrationRun::query()->latest('id')->firstOrFail();
}

/**
 * @param  array<string, mixed>  $attributes
 */
function legacyUser(array $attributes): void
{
    test()->legacy->table('usuarios')->insert([
        'Contrasena' => Hash::make('clave-legacy'),
        ...$attributes,
    ]);
}

test('maps legacy roles to base roles and creates the ones without equivalent', function () {
    $run = startLegacyRun(['roles']);

    expect($run->status)->toBe(MigrationRunStatus::Completed)
        ->and($run->steps->sole()->only('total_count', 'read_count', 'inserted_count', 'updated_count'))
        ->toBe(['total_count' => 4, 'read_count' => 4, 'inserted_count' => 1, 'updated_count' => 3]);

    $this->tenant->run(function () {
        $administrative = Role::query()->where('base_key', BaseRole::Administrative->value)->sole();
        $creator = Role::query()->where('legacy_id', '4')->sole();

        expect($administrative->legacy_id)->toBe('2')
            ->and($creator->only('name', 'base_key', 'legacy_source'))->toBe(['name' => 'Creador', 'base_key' => null, 'legacy_source' => 'roles'])
            ->and(Role::administrator()->permissions)->toHaveCount(count(Permission::cases()));
    });
});

test('grants a permission only when the legacy role could complete the action', function () {
    startLegacyRun(['roles']);

    $this->tenant->run(function () {
        // Sin el permiso de carpeta, la página de edición legacy no podía guardar.
        expect(Role::query()->where('legacy_id', '2')->sole()->permissions->pluck('name')->all())->toBe(['users.view'])
            ->and(Role::query()->where('legacy_id', '4')->sole()->permissions->pluck('name')->sort()->values()->all())
            ->toBe(['users.create', 'users.delete', 'users.view']);
    });
});

test('reports the legacy permissions of modules that do not exist yet', function () {
    $run = startLegacyRun(['roles']);

    $warning = $run->issues()->where('severity', MigrationIssueSeverity::Warning)->sole();
    expect($warning->payload)->toBe(['modules' => ['Portal de aliados' => 1, 'Órdenes de servicio' => 1]]);
});

test('stops the roles step when a legacy permission url is not mapped', function () {
    LegacyDatabase::role($this->legacy, 5, 'Coordinador', ['/inventado/index.php']);

    $run = startLegacyRun(['roles']);

    expect($run->status)->toBe(MigrationRunStatus::Failed)
        ->and($run->steps->sole()->error)->toContain('/inventado/index.php');
    $this->tenant->run(fn () => expect(Role::query()->whereNotNull('legacy_id')->exists())->toBeFalse());
});

test('stops the roles step when a legacy role does not match the mapping', function () {
    $this->legacy->table('roles')->where('id', 2)->update(['nombre' => 'Gerente']);

    $run = startLegacyRun(['roles']);

    expect($run->steps->sole()->error)->toBe('El rol legacy #2 se llama "Gerente" y el mapeo espera "Administrativo".');
    $this->tenant->run(fn () => expect(Role::query()->whereNotNull('legacy_id')->exists())->toBeFalse());
});

test('imports users with their role, their legacy password and the data of their entity', function () {
    $this->legacy->table('empleados')->insert(['ID' => 7, 'Nombre' => 'Laura Gómez', 'Correo' => ' Laura@Kemuel.test ']);
    legacyUser(['ID' => 31, 'NombreUsuario' => 'Laura.Gomez', 'EmpleadoID' => 7, 'rol_id' => 2]);

    $run = startLegacyRun();

    expect($run->steps->firstWhere('module', 'users')->inserted_count)->toBe(1);
    $this->tenant->run(function () {
        $user = User::query()->where('legacy_id', '31')->sole();

        expect($user->only('name', 'username', 'email', 'legacy_source'))
            ->toBe(['name' => 'Laura Gómez', 'username' => 'laura.gomez', 'email' => 'laura@kemuel.test', 'legacy_source' => 'usuarios'])
            ->and(Hash::check('clave-legacy', $user->password))->toBeTrue()
            ->and($user->roles->sole()->legacy_id)->toBe('2');
    });
});

test('names a client user after its business name or, for a person, its names', function () {
    $this->legacy->table('clientes')->insert([
        ['ID' => 1, 'RazonSocial' => 'Colegio Andino', 'Nombres' => null, 'Apellidos' => null],
        ['ID' => 2, 'RazonSocial' => '', 'Nombres' => 'Pedro', 'Apellidos' => 'Ruiz'],
    ]);
    legacyUser(['ID' => 1, 'NombreUsuario' => 'andino', 'ClienteID' => 1, 'rol_id' => 2]);
    legacyUser(['ID' => 2, 'NombreUsuario' => 'pruiz', 'ClienteID' => 2, 'rol_id' => 2]);

    startLegacyRun();

    $this->tenant->run(fn () => expect(User::query()->whereNotNull('legacy_id')->orderBy('legacy_id')->pluck('name')->all())
        ->toBe(['Colegio Andino', 'Pedro Ruiz']));
});

test('keeps a repeated email only on the employee account', function () {
    $this->legacy->table('aliados')->insert(['id' => 3, 'nombre' => 'Laura Gómez (aliada)', 'email' => 'laura@kemuel.test']);
    $this->legacy->table('empleados')->insert(['ID' => 7, 'Nombre' => 'Laura Gómez', 'Correo' => 'laura@kemuel.test']);
    legacyUser(['ID' => 1, 'NombreUsuario' => 'laura.aliada', 'AliadoID' => 3, 'rol_id' => 8]);
    legacyUser(['ID' => 2, 'NombreUsuario' => 'laura', 'EmpleadoID' => 7, 'rol_id' => 2]);

    $run = startLegacyRun();

    $this->tenant->run(fn () => expect(User::query()->whereNotNull('legacy_id')->orderBy('legacy_id')->pluck('email', 'username')->all())
        ->toBe(['laura.aliada' => null, 'laura' => 'laura@kemuel.test']));
    expect($run->issues()->where('legacy_id', '1')->sole()->message)
        ->toBe('Comparte el correo laura@kemuel.test con el usuario "laura" (usuarios #2), que se queda con él por ser la cuenta de empleado. '
            .'Esta cuenta se importa sin correo: sigue entrando con su usuario "laura.aliada" y su contraseña de siempre; '
            .'solo no podrá recuperar la contraseña por correo.');
});

test('quarantines the users that cannot be imported without inventing data', function () {
    $this->tenant->run(fn () => User::factory()->create(['username' => 'ocupado', 'email' => 'otro@kemuel.test']));
    $this->legacy->table('empleados')->insert([
        ['ID' => 1, 'Nombre' => 'Sin Rol', 'Correo' => 'sinrol@kemuel.test'],
        ['ID' => 2, 'Nombre' => 'Doble', 'Correo' => 'doble@kemuel.test'],
        ['ID' => 3, 'Nombre' => 'Ocupado', 'Correo' => 'ocupado@kemuel.test'],
        ['ID' => 4, 'Nombre' => 'Sin Clave', 'Correo' => 'sinclave@kemuel.test'],
    ]);
    $this->legacy->table('aliados')->insert(['id' => 1, 'nombre' => 'Doble', 'email' => null]);
    legacyUser(['ID' => 1, 'NombreUsuario' => 'sinrol', 'EmpleadoID' => 1, 'rol_id' => null]);
    legacyUser(['ID' => 2, 'NombreUsuario' => 'doble', 'EmpleadoID' => 2, 'AliadoID' => 1, 'rol_id' => 2]);
    legacyUser(['ID' => 3, 'NombreUsuario' => 'ocupado', 'EmpleadoID' => 3, 'rol_id' => 2]);
    legacyUser(['ID' => 4, 'NombreUsuario' => 'sinclave', 'EmpleadoID' => 4, 'rol_id' => 2, 'Contrasena' => md5('x')]);
    legacyUser(['ID' => 5, 'NombreUsuario' => 'fantasma', 'EmpleadoID' => 99, 'rol_id' => 2]);
    legacyUser(['ID' => 6, 'NombreUsuario' => 'jefe', 'EmpleadoID' => 1, 'rol_id' => 3]);

    $run = startLegacyRun();

    expect($run->steps->firstWhere('module', 'users')->only('read_count', 'inserted_count', 'quarantined_count'))
        ->toBe(['read_count' => 6, 'inserted_count' => 0, 'quarantined_count' => 6])
        ->and($run->issues()->where('severity', MigrationIssueSeverity::Quarantine)->orderBy('legacy_id')->pluck('message', 'legacy_id')->all())
        ->toBe([
            '1' => 'No tiene rol.',
            '2' => 'Debe estar vinculado a exactamente una entidad (empleado, aliado, proveedor o cliente) y tiene 2.',
            '3' => 'Choca con el usuario existente "ocupado" (mismo nombre de usuario o correo).',
            '4' => 'La contraseña no está en un formato bcrypt que se pueda conservar.',
            '5' => 'El empleado #99 al que está vinculado no existe en la fuente.',
            '6' => 'Su rol legacy #3 no está importado en la empresa.',
        ]);
    $this->tenant->run(fn () => expect(User::query()->whereNotNull('legacy_id')->exists())->toBeFalse());
});

test('running the migration again updates the imported records instead of duplicating them', function () {
    $this->legacy->table('empleados')->insert(['ID' => 7, 'Nombre' => 'Laura Gómez', 'Correo' => 'laura@kemuel.test']);
    legacyUser(['ID' => 31, 'NombreUsuario' => 'laura', 'EmpleadoID' => 7, 'rol_id' => 2]);
    startLegacyRun();
    $this->legacy->table('usuarios')->where('ID', 31)->update(['rol_id' => 4]);

    $run = startLegacyRun();

    expect($run->steps()->orderBy('id')->pluck('updated_count', 'module')->all())->toBe(['roles' => 4, 'users' => 1]);
    $this->tenant->run(function () {
        expect(Role::query()->where('name', 'Creador')->count())->toBe(1)
            ->and(User::query()->where('legacy_id', '31')->sole()->roles->sole()->name)->toBe('Creador');
    });
});

test('a dry run reports what it would import without writing in the tenant', function () {
    $this->legacy->table('empleados')->insert(['ID' => 7, 'Nombre' => 'Laura Gómez', 'Correo' => 'laura@kemuel.test']);
    legacyUser(['ID' => 31, 'NombreUsuario' => 'laura', 'EmpleadoID' => 7, 'rol_id' => 4]);

    $run = startLegacyRun(mode: 'dry_run');

    expect($run->status)->toBe(MigrationRunStatus::Completed)
        ->and($run->steps()->orderBy('id')->pluck('inserted_count', 'module')->all())->toBe(['roles' => 1, 'users' => 1]);
    $this->tenant->run(function () {
        expect(User::query()->whereNotNull('legacy_id')->exists())->toBeFalse()
            ->and(Role::query()->whereNotNull('legacy_id')->exists())->toBeFalse();
    });
});

test('imports the company configuration and warns when the business name differs', function () {
    $run = startLegacyRun(['company']);

    expect($run->status)->toBe(MigrationRunStatus::Completed)
        ->and($run->steps->sole()->inserted_count)->toBe(1)
        ->and($run->issues()->pluck('message')->all())->toContain(
            "La razón social del legacy (\"Transportes de prueba SAS\") es distinta a la registrada en el panel central (\"{$this->tenant->name}\"). Los documentos usan la del panel central; corrígela allí si hace falta.",
        );
    $this->tenant->run(fn () => expect(CompanyProfile::current()->only('email', 'territorial_code', 'habilitation_resolution', 'habilitation_year', 'legacy_id'))
        ->toBe(['email' => 'contacto@transportes.test', 'territorial_code' => '213', 'habilitation_resolution' => '0114', 'habilitation_year' => '16', 'legacy_id' => '1']));
});

test('quarantines the company configuration when its habilitation data is malformed', function () {
    $this->legacy->table('configuracion_empresa')->update(['codigo_territorial' => '21A']);

    $run = startLegacyRun(['company']);

    expect($run->steps->sole()->quarantined_count)->toBe(1);
    $this->tenant->run(fn () => expect(CompanyProfile::current()->territorial_code)->toBeNull());
});

test('imports allies and suppliers linking as mixed only the reciprocal pairs', function () {
    $this->legacy->table('aliados')->insert([
        ['id' => 1, 'nombre' => ' MURIEL  ROSALES ', 'nit' => '45502488 ', 'telefono' => '3116894076', 'direccion' => null, 'email' => ' Murolle@Hotmail.com', 'proveedor_id' => 36],
        ['id' => 3, 'nombre' => 'SANTIAGO PAJARO', 'nit' => '1043652341', 'telefono' => null, 'direccion' => null, 'email' => null, 'proveedor_id' => null],
    ]);
    $this->legacy->table('proveedores')->insert([
        ['id' => 36, 'nombre' => 'MURIEL ROSALES', 'nit' => '45502488', 'telefono' => null, 'direccion' => null, 'email' => null, 'aliado_id' => 1],
        ['id' => 40, 'nombre' => 'HUERFANO', 'nit' => '900', 'telefono' => null, 'direccion' => null, 'email' => null, 'aliado_id' => 3],
    ]);

    $run = startLegacyRun(['third_parties']);

    expect($run->steps->sole()->only('total_count', 'inserted_count'))->toBe(['total_count' => 4, 'inserted_count' => 4])
        ->and($run->issues()->where('legacy_id', '40')->sole()->message)
        ->toBe('El proveedor apunta al aliado #3, pero ese aliado no apunta de vuelta: no se vinculan como mixto.');
    $this->tenant->run(function () {
        $ally = Ally::query()->where('legacy_id', '1')->sole();

        expect($ally->only('name', 'nit', 'email'))->toBe(['name' => 'MURIEL ROSALES', 'nit' => '45502488', 'email' => 'murolle@hotmail.com'])
            ->and(Supplier::query()->where('legacy_id', '36')->sole()->ally_id)->toBe($ally->id)
            ->and(Supplier::query()->where('legacy_id', '40')->sole()->ally_id)->toBeNull();
    });
});

test('imports repeated suppliers and reports them', function () {
    $this->legacy->table('proveedores')->insert([
        ['id' => 11, 'nombre' => 'LAURA ROSALES BUSTAMANTE', 'nit' => '10049153520', 'aliado_id' => null],
        ['id' => 12, 'nombre' => 'LAURA ROSALES BUSTAMANTE', 'nit' => '10049153520', 'aliado_id' => null],
    ]);

    $run = startLegacyRun(['third_parties']);

    $this->tenant->run(fn () => expect(Supplier::count())->toBe(2));
    expect($run->issues()->pluck('message')->all())->toContain(
        'Hay 2 proveedores con el mismo NIT "10049153520" (#11, #12). Se migran todos; conviene depurarlos.',
        'Hay 2 proveedores con el mismo nombre "laura rosales bustamante" (#11, #12). Se migran todos; conviene depurarlos.',
    );
});

test('links each imported user to its ally or supplier whatever module runs first', function (array $runs) {
    $this->legacy->table('aliados')->insert(['id' => 1, 'nombre' => 'ALIADO UNO', 'email' => 'aliado@kemuel.test']);
    $this->legacy->table('proveedores')->insert(['id' => 5, 'nombre' => 'PROVEEDOR CINCO', 'email' => 'proveedor@kemuel.test']);
    legacyUser(['ID' => 1, 'NombreUsuario' => 'aliado1', 'AliadoID' => 1, 'rol_id' => 8]);
    legacyUser(['ID' => 2, 'NombreUsuario' => 'proveedor5', 'ProveedorID' => 5, 'rol_id' => 8]);

    foreach ($runs as $modules) {
        startLegacyRun($modules);
    }

    $this->tenant->run(function () {
        expect(User::query()->where('username', 'aliado1')->sole()->ally?->name)->toBe('ALIADO UNO')
            ->and(User::query()->where('username', 'proveedor5')->sole()->supplier?->name)->toBe('PROVEEDOR CINCO');
    });
})->with([
    'same run' => [[['roles', 'third_parties', 'users']]],
    'users first' => [[['roles', 'users'], ['third_parties']]],
]);

test('grants the ally and supplier permissions the legacy role could complete', function () {
    LegacyDatabase::role($this->legacy, 5, 'Coordinador', ['/proveedores/', '/proveedores/create.php', '/proveedores_aliados/index.php', '/proveedores_aliados/update.php']);

    startLegacyRun(['roles']);

    $this->tenant->run(fn () => expect(Role::query()->where('legacy_id', '5')->sole()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['allies.view', 'suppliers.create', 'suppliers.link', 'suppliers.view']));
});

test('imports the clients of the company and of each ally keeping their data', function () {
    $this->legacy->table('aliados')->insert(['id' => 55, 'nombre' => 'ALIADO 55']);
    $this->legacy->table('clientes')->insert([
        ['ID' => 1, 'Tipo' => 'Empresa', 'TipoIdentificacion' => 'NIT', 'Identificacion' => '830085844', 'DV' => '1', 'RazonSocial' => 'WOOD ENGINEERING SAS', 'Telefono' => '300 443 3162', 'estado' => 'Activo', 'aliadoid' => null],
        ['ID' => 127, 'Tipo' => 'Persona', 'TipoIdentificacion' => 'Cédula de Ciudadanía', 'Identificacion' => '1043652341', 'DV' => null, 'RazonSocial' => null, 'Telefono' => '300-44', 'estado' => 'Inactivo', 'aliadoid' => 55],
    ]);

    $run = startLegacyRun(['third_parties', 'clients']);

    expect($run->steps()->where('module', 'clients')->sole()->inserted_count)->toBe(2)
        ->and($run->issues()->pluck('message')->all())->toContain('1 clientes con teléfono que no tiene entre 7 y 15 dígitos. Se migran como están; al editarlos se pedirá corregirlo.');
    $this->tenant->run(function () {
        $company = Client::query()->where('legacy_id', '1')->sole();
        $allyClient = Client::query()->where('legacy_id', '127')->sole();

        expect($company->only('type', 'phone', 'ally_id', 'is_active'))->toBe(['type' => ClientType::Company, 'phone' => '3004433162', 'ally_id' => null, 'is_active' => true])
            ->and($allyClient->only('phone', 'is_active'))->toBe(['phone' => '300-44', 'is_active' => false])
            ->and($allyClient->ally->name)->toBe('ALIADO 55');
    });
});

test('quarantines a client whose owner or type cannot be known', function () {
    $this->legacy->table('clientes')->insert([
        ['ID' => 1, 'Tipo' => 'Persona', 'aliadoid' => 99, 'estado' => 'Activo'],
        ['ID' => 2, 'Tipo' => 'Gobierno', 'aliadoid' => null, 'estado' => 'Activo'],
    ]);

    $run = startLegacyRun(['third_parties', 'clients']);

    expect($run->issues()->where('severity', MigrationIssueSeverity::Quarantine)->orderBy('legacy_id')->pluck('message', 'legacy_id')->all())->toBe([
        '1' => 'Su aliado #99 no está importado: no se sabe a quién pertenece. Migra primero aliados y proveedores.',
        '2' => 'Tipo de cliente desconocido: "Gobierno".',
    ]);
    $this->tenant->run(fn () => expect(Client::count())->toBe(0));
});

test('reports the clients repeated within the same owner', function () {
    $this->legacy->table('clientes')->insert([
        ['ID' => 10, 'Identificacion' => '900123456', 'CodigoSucursal' => null, 'aliadoid' => null],
        ['ID' => 11, 'Identificacion' => '900123456', 'CodigoSucursal' => null, 'aliadoid' => null],
        ['ID' => 12, 'Identificacion' => '900123456', 'CodigoSucursal' => '13001', 'aliadoid' => null],
    ]);

    $run = startLegacyRun(['third_parties', 'clients']);

    $this->tenant->run(fn () => expect(Client::count())->toBe(3));
    expect($run->issues()->pluck('message')->all())
        ->toContain('2 clientes de la empresa con la identificación 900123456 (#10, #11). Se migran todos; conviene depurarlos.');
});

test('imports the routes keeping their creation date', function () {
    $this->legacy->table('rutas')->insert([
        ['ID' => 1, 'PuntoInicio' => ' Centro ', 'PuntoFin' => 'Villaestrella', 'Descripcion' => 'Ruta fija', 'TipoRuta' => 'montañosa', 'Estado' => 'Mantenimiento', 'FechaCreacion' => '2025-05-05 09:51:49'],
        ['ID' => 3, 'PuntoInicio' => 'A', 'PuntoFin' => 'B', 'Descripcion' => null, 'TipoRuta' => 'aérea', 'Estado' => 'Activa', 'FechaCreacion' => null],
    ]);

    $run = startLegacyRun(['routes']);

    expect($run->steps->sole()->only('inserted_count', 'quarantined_count'))->toBe(['inserted_count' => 1, 'quarantined_count' => 1])
        ->and($run->issues()->pluck('message')->all())->toContain('Tipo de ruta desconocido: "aérea".');
    $this->tenant->run(function () {
        $route = TransportRoute::query()->where('legacy_id', '1')->sole();

        expect($route->only('start_point', 'type', 'status'))->toBe(['start_point' => 'Centro', 'type' => RouteType::Mountain, 'status' => RouteStatus::Maintenance])
            ->and($route->created_at->toDateTimeString())->toBe('2025-05-05 09:51:49');
    });
});

test('loads repeated routes only once and points the duplicates to it', function () {
    $this->legacy->table('rutas')->insert([
        ['ID' => 4, 'PuntoInicio' => 'Centro', 'PuntoFin' => 'Villaestrella', 'Descripcion' => null, 'Estado' => 'Mantenimiento'],
        ['ID' => 7, 'PuntoInicio' => ' centro', 'PuntoFin' => 'VILLAESTRELLA ', 'Descripcion' => 'Ruta fija', 'Estado' => 'Activa'],
        ['ID' => 9, 'PuntoInicio' => 'CENTRO', 'PuntoFin' => 'villaestrella', 'Descripcion' => null, 'Estado' => 'Inactiva'],
    ]);
    // Una corrida anterior (antes de unificar) había cargado una de las repetidas por separado.
    $this->tenant->run(fn () => TransportRoute::factory()->create(['start_point' => 'centro', 'end_point' => 'VILLAESTRELLA'])
        ->forceFill(['legacy_source' => 'rutas', 'legacy_id' => '7'])->save());

    $run = startLegacyRun(['routes']);

    expect($run->steps->sole()->only('read_count', 'inserted_count', 'skipped_count'))->toBe(['read_count' => 3, 'inserted_count' => 1, 'skipped_count' => 2])
        ->and($run->issues()->pluck('message')->all())->toContain('3 rutas "Centro - Villaestrella" eran la misma: se unificaron en la #4 (también #7, #9).');
    $this->tenant->run(function () {
        $route = TransportRoute::query()->sole();

        expect($route->only('legacy_id', 'description', 'status'))->toBe(['legacy_id' => '4', 'description' => 'Ruta fija', 'status' => RouteStatus::Active])
            ->and(LegacyAlias::resolve(TransportRoute::class, 'rutas', 9))->toBe($route->id)
            ->and(LegacyAlias::resolve(TransportRoute::class, 'rutas', 4))->toBe($route->id);
    });
});

test('refuses a source that belongs to another company', function () {
    $this->legacy->table('configuracion_empresa')->update(['nit' => '800123456']);

    $run = startLegacyRun();

    expect($run->status)->toBe(MigrationRunStatus::Failed)
        ->and($run->steps()->orderBy('id')->pluck('status')->unique()->all())->toBe([MigrationRunStatus::Pending])
        ->and($run->issues()->sole()->message)->toBe("El NIT de la fuente (800123456) no coincide con el de la empresa {$this->tenant->name} (900980138).");
    $this->tenant->run(fn () => expect(Role::query()->whereNotNull('legacy_id')->exists())->toBeFalse());
});
