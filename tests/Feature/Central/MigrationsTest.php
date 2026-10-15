<?php

use App\Enums\MigrationSourceType;
use App\Models\CentralUser;
use App\Models\MigrationIssue;
use App\Models\MigrationRun;
use App\Models\MigrationRunStep;
use App\Models\MigrationSource;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

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
        ->where('issues.0.legacy_id', '17')
        ->where('issuesCount', 1)
    );
});

test('the migrations module requires a central administrator session', function () {
    auth('central')->logout();

    $this->get(route('central.migrations.index'))->assertRedirect(route('central.login'));
});
