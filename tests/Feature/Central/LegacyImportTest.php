<?php

use App\Enums\BaseRole;
use App\Enums\MigrationIssueSeverity;
use App\Enums\MigrationRunStatus;
use App\Enums\Permission;
use App\Models\CentralUser;
use App\Models\MigrationRun;
use App\Models\MigrationSource;
use App\Models\Role;
use App\Models\Tenant;
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

test('refuses a source that belongs to another company', function () {
    $this->legacy->table('configuracion_empresa')->update(['nit' => '800123456']);

    $run = startLegacyRun();

    expect($run->status)->toBe(MigrationRunStatus::Failed)
        ->and($run->steps()->orderBy('id')->pluck('status')->unique()->all())->toBe([MigrationRunStatus::Pending])
        ->and($run->issues()->sole()->message)->toBe("El NIT de la fuente (800123456) no coincide con el de la empresa {$this->tenant->name} (900980138).");
    $this->tenant->run(fn () => expect(Role::query()->whereNotNull('legacy_id')->exists())->toBeFalse());
});
