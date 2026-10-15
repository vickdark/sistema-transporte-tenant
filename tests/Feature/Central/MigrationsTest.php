<?php

use App\Enums\MigrationSourceType;
use App\Models\CentralUser;
use App\Models\MigrationIssue;
use App\Models\MigrationRun;
use App\Models\MigrationRunStep;
use App\Models\MigrationSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ExportedSpreadsheet;
use Tests\Support\LegacyFilesServer;

beforeEach(function () {
    $this->admin = CentralUser::factory()->create();
    $this->actingAs($this->admin, 'central');
});

test('registers a legacy source with encrypted credentials', function () {
    $this->post(route('central.migrations.sources.store'), [
        'name' => 'Copia Kemuel',
        'host' => '10.0.0.5',
        'port' => 3306,
        'database' => 'kemuel_copy',
        'username' => 'reader',
        'password' => 's3cret-value',
    ])->assertRedirect(route('central.migrations.index'));

    $source = MigrationSource::query()->sole();
    expect($source->type)->toBe(MigrationSourceType::MysqlConnection)
        ->and($source->credentials)->toMatchArray(['host' => '10.0.0.5', 'port' => 3306, 'password' => 's3cret-value'])
        ->and($source->created_by)->toBe($this->admin->id)
        ->and(DB::table('migration_sources')->value('credentials'))->not->toContain('s3cret-value');
});

test('requires the connection data of a legacy source', function () {
    $this->post(route('central.migrations.sources.store'), ['port' => 'abc'])
        ->assertSessionHasErrors(['name', 'host', 'port', 'database', 'username']);

    expect(MigrationSource::count())->toBe(0);
});

test('never sends source credentials to the frontend', function () {
    MigrationSource::factory()->create(['credentials' => [
        'host' => 'db.test', 'port' => 3306, 'database' => 'legacy', 'username' => 'reader', 'password' => 's3cret-value',
    ]]);

    $response = $this->get(route('central.migrations.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('central/migrations/Index')
        ->where('sources.0.host', 'db.test')
        ->missing('sources.0.credentials')
    );
    expect($response->getContent())->not->toContain('s3cret-value');
});

describe('files access', function () {
    beforeEach(function () {
        $this->server = LegacyFilesServer::fake();
        File::ensureDirectoryExists("{$this->server}/var/www/legacy/uploads");
    });

    afterEach(fn () => File::deleteDirectory($this->server));

    test('registers the SFTP access to the legacy files encrypted, accepting its uploads folder too', function () {
        $this->post(route('central.migrations.sources.store'), [
            'name' => 'Copia Kemuel', 'host' => '10.0.0.5', 'port' => 3306, 'database' => 'kemuel', 'username' => 'reader',
            'files_host' => 'legacy.test', 'files_port' => 22, 'files_username' => 'lector', 'files_password' => 'secreto-sftp',
            'files_root' => '/var/www/legacy/uploads/',
        ])->assertSessionHasNoErrors();

        expect(MigrationSource::query()->sole()->files_connection)->toBe(LegacyFilesServer::connection())
            ->and(DB::table('migration_sources')->value('files_connection'))->not->toContain('secreto-sftp');
        $this->get(route('central.migrations.index'))->assertInertia(fn (Assert $page) => $page->where('sources.0.files', 'lector@legacy.test:/var/www/legacy'));
    });

    test('checks the connection and that the folder contains uploads', function () {
        $source = MigrationSource::factory()->create(['name' => 'Copia']);
        $payload = ['name' => 'Copia', 'files_host' => 'legacy.test', 'files_port' => 22, 'files_username' => 'lector', 'files_password' => 'x'];

        $this->put(route('central.migrations.sources.update', $source), [...$payload, 'files_root' => '/var/www'])
            ->assertSessionHasErrors(['files_root' => 'En el servidor no existe /var/www/uploads: escribe la carpeta del legacy que contiene uploads/.']);
        $this->put(route('central.migrations.sources.update', $source), [...$payload, 'files_host' => LegacyFilesServer::UNREACHABLE_HOST, 'files_root' => '/var/www/legacy'])
            ->assertSessionHasErrors('files_host');
        expect(session('errors')->first('files_host'))->toStartWith('No se pudo conectar por SFTP a sin-acceso.test: Cannot connect to 127.0.0.1:1.');
        $this->put(route('central.migrations.sources.update', $source), [...$payload, 'files_password' => '', 'files_root' => 'var/www/legacy'])
            ->assertSessionHasErrors([
                'files_password' => 'Escribe la contraseña SFTP o pega la llave privada.',
                'files_root' => 'Escribe la ruta completa en el servidor, desde la raíz (p. ej. /var/www/mi-legacy).',
            ]);

        expect($source->refresh()->files_connection)->toBeNull();
    });

    test('tests the access without saving it and lists the folders of uploads', function () {
        File::ensureDirectoryExists("{$this->server}/var/www/legacy/uploads/vehiculos");
        File::ensureDirectoryExists("{$this->server}/var/www/legacy/uploads/documentos_vehiculos");
        $source = MigrationSource::factory()->create(['files_connection' => LegacyFilesServer::connection()]);
        $payload = ['files_host' => 'legacy.test', 'files_port' => 22, 'files_username' => 'lector', 'files_root' => '/var/www/legacy/'];

        $this->postJson(route('central.migrations.sources.test-files'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['files_password' => 'Escribe la contraseña SFTP o pega la llave privada.']);
        $this->postJson(route('central.migrations.sources.test-files'), [...$payload, 'source_id' => $source->id])
            ->assertOk()
            ->assertJson(['message' => 'Conexión correcta. uploads/ tiene 2 carpetas: documentos_vehiculos, vehiculos.']);
        $this->postJson(route('central.migrations.sources.test-files'), [...$payload, 'files_password' => 'x', 'files_root' => '/var/www'])
            ->assertJsonValidationErrors(['files_root' => 'En el servidor no existe /var/www/uploads: escribe la carpeta del legacy que contiene uploads/.']);

        expect(MigrationSource::query()->count())->toBe(1);
    });

    test('editing keeps the saved secret when none is written and never sends it to the frontend', function () {
        $source = MigrationSource::factory()->create(['files_connection' => LegacyFilesServer::connection()]);

        $this->get(route('central.migrations.sources.edit', $source))->assertInertia(fn (Assert $page) => $page
            ->where('source.files.has_password', true)
            ->missing('source.files.password')
        );

        $this->put(route('central.migrations.sources.update', $source), [
            'name' => 'Copia', 'files_host' => 'legacy.test', 'files_port' => 2222, 'files_username' => 'lector', 'files_password' => '', 'files_root' => '/var/www/legacy',
        ])->assertRedirect(route('central.migrations.index'));

        expect($source->refresh()->files_connection)->toBe([...LegacyFilesServer::connection(), 'port' => 2222]);

        $this->put(route('central.migrations.sources.update', $source), ['name' => 'Copia', 'files_host' => ''])->assertSessionHasNoErrors();
        expect($source->refresh()->files_connection)->toBeNull();
    });
});

test('deletes a source without runs but keeps one that has runs', function () {
    $unused = MigrationSource::factory()->create();
    $used = MigrationRun::factory()->create()->source;

    $this->delete(route('central.migrations.sources.destroy', $unused));
    $this->delete(route('central.migrations.sources.destroy', $used))
        ->assertSessionHasErrors(['source' => 'No se puede eliminar una fuente que ya tiene corridas.']);

    expect(MigrationSource::query()->pluck('id')->all())->toBe([$used->id]);
});

test('shows a run with its steps and issues', function () {
    $run = MigrationRun::factory()->create();
    MigrationRunStep::factory()->for($run, 'run')->create(['module' => 'users', 'read_count' => 184, 'quarantined_count' => 2]);
    MigrationIssue::factory()->for($run, 'run')->create(['legacy_table' => 'usuarios', 'legacy_id' => '17']);

    $this->get(route('central.migrations.runs.show', $run))->assertInertia(fn (Assert $page) => $page
        ->component('central/migrations/runs/Show')
        ->where('run.tenant', $run->tenant->name)
        ->where('steps.0.read_count', 184)
        ->where('steps.0.quarantined_count', 2)
        ->where('issues.data.0.legacy_id', '17')
        ->where('issues.total', 1)
    );
});

test('a summary warning shows and exports the legacy records it affects, and they can be searched', function () {
    $run = MigrationRun::factory()->create();
    MigrationIssue::factory()->for($run, 'run')->create(['severity' => 'warning', 'legacy_table' => 'partes_vehiculo', 'legacy_id' => null, 'message' => '2 registros de partes_vehiculo: km 0.', 'payload' => ['legacy_ids' => [501, 733]]]);
    MigrationIssue::factory()->for($run, 'run')->create(['severity' => 'warning', 'message' => 'Aviso general']);

    $this->get(route('central.migrations.runs.show', ['run' => $run, 'search' => '733']))->assertInertia(fn (Assert $page) => $page
        ->where('issues.total', 1)
        ->where('issues.data.0.affected_ids', ['501', '733'])
    );
    expect(ExportedSpreadsheet::rows($this->get(route('central.migrations.runs.issues.export', ['run' => $run, 'format' => 'xlsx'])))[1][3])->toBe('501, 733');
});

test('exports the quarantine report of a run filtered by type', function () {
    $run = MigrationRun::factory()->create();
    MigrationIssue::factory()->for($run, 'run')->create(['severity' => 'quarantine', 'legacy_table' => 'usuarios', 'legacy_id' => '17', 'message' => 'No tiene rol.']);
    MigrationIssue::factory()->for($run, 'run')->create(['severity' => 'warning', 'message' => 'Aviso general']);
    MigrationIssue::factory()->create(['severity' => 'quarantine', 'message' => 'De otra corrida']);

    $response = $this->get(route('central.migrations.runs.issues.export', ['run' => $run, 'format' => 'xlsx', 'severity' => 'quarantine']));

    $response->assertOk()->assertDownload();
    expect(ExportedSpreadsheet::rows($response))->toBe([
        ['Tipo', 'Paso', 'Tabla legacy', 'ID legacy', 'Detalle'],
        ['Cuarentena', '', 'usuarios', '17', 'No tiene rol.'],
    ]);
});

test('lists the runs paginated and filtered by status in the server', function () {
    MigrationRun::factory()->create(['status' => 'failed']);
    $completed = MigrationRun::factory()->create(['status' => 'completed']);

    $this->get(route('central.migrations.index', ['status' => 'completed']))->assertInertia(fn (Assert $page) => $page
        ->has('runs.data', 1)
        ->where('runs.data.0.id', $completed->id)
        ->where('query.status', 'completed')
    );
});

test('the migrations module requires a central administrator session', function () {
    auth('central')->logout();

    $this->get(route('central.migrations.index'))->assertRedirect(route('central.login'));
});
