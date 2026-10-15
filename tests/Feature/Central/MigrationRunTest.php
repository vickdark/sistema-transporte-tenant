<?php

use App\Actions\Migrations\MigrationRunQueueStatus;
use App\Enums\MigrationRunMode;
use App\Enums\MigrationRunStatus;
use App\Jobs\ExecuteMigrationRun;
use App\Models\CentralUser;
use App\Models\MigrationRun;
use App\Models\MigrationRunStep;
use App\Models\MigrationSource;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = CentralUser::factory()->create();
    $this->actingAs($this->admin, 'central');

    $this->tenant = Tenant::factory()->create();
    $this->source = MigrationSource::factory()->create();
});

/**
 * @return array<string, mixed>
 */
function migrationRunPayload(array $overrides = []): array
{
    return [
        'migration_source_id' => test()->source->id,
        'tenant_id' => test()->tenant->id,
        'mode' => 'dry_run',
        'modules' => ['users', 'roles'],
        ...$overrides,
    ];
}

test('starts a run in the queue with one step per module in dependency order', function () {
    Queue::fake();

    $response = $this->post(route('central.migrations.runs.store'), migrationRunPayload());

    $run = MigrationRun::query()->sole();
    $response->assertRedirect(route('central.migrations.runs.show', $run));
    expect($run->only('status', 'mode', 'modules', 'started_by'))->toBe([
        'status' => MigrationRunStatus::Pending,
        'mode' => MigrationRunMode::DryRun,
        'modules' => ['roles', 'users'],
        'started_by' => $this->admin->id,
    ])->and($run->steps()->orderBy('id')->pluck('module')->all())->toBe(['roles', 'users']);
    Queue::assertPushed(ExecuteMigrationRun::class, fn (ExecuteMigrationRun $job) => $job->run->is($run));
});

test('validates the run options', function () {
    $this->post(route('central.migrations.runs.store'), [
        'migration_source_id' => 999,
        'tenant_id' => 'missing',
        'mode' => 'forever',
        'modules' => ['payroll'],
    ])->assertSessionHasErrors(['migration_source_id', 'tenant_id', 'mode', 'modules.0']);

    expect(MigrationRun::count())->toBe(0);
});

test('requires the dependencies of a module in the same run or in a finished live run', function () {
    Queue::fake();

    $this->post(route('central.migrations.runs.store'), migrationRunPayload(['modules' => ['users']]))
        ->assertSessionHasErrors(['modules' => 'Usuarios necesita Roles y permisos: inclúyelo en la corrida o mígralo antes en una corrida real.']);

    MigrationRunStep::factory()
        ->for(MigrationRun::factory()->for($this->tenant)->create(['mode' => MigrationRunMode::Live, 'status' => MigrationRunStatus::Completed]), 'run')
        ->create(['module' => 'roles', 'status' => MigrationRunStatus::Completed]);

    $this->post(route('central.migrations.runs.store'), migrationRunPayload(['modules' => ['users']]))
        ->assertSessionHasNoErrors();
});

test('a dry run does not count as having migrated a dependency', function () {
    MigrationRunStep::factory()
        ->for(MigrationRun::factory()->for($this->tenant)->create(['mode' => MigrationRunMode::DryRun, 'status' => MigrationRunStatus::Completed]), 'run')
        ->create(['module' => 'roles', 'status' => MigrationRunStatus::Completed]);

    $this->post(route('central.migrations.runs.store'), migrationRunPayload(['modules' => ['users']]))
        ->assertSessionHasErrors('modules');
});

test('does not start a second run while the tenant has an active one', function () {
    MigrationRun::factory()->for($this->tenant)->create(['status' => MigrationRunStatus::Running]);

    $this->post(route('central.migrations.runs.store'), migrationRunPayload())
        ->assertSessionHasErrors(['tenant_id' => 'La empresa ya tiene una corrida en curso: espera a que termine o cancélala.']);

    expect(MigrationRun::count())->toBe(1);
});

test('cancelling a pending run keeps it from executing', function () {
    $run = MigrationRun::factory()->create();
    $step = MigrationRunStep::factory()->for($run, 'run')->create(['module' => 'roles']);

    $this->post(route('central.migrations.runs.cancel', $run))->assertSessionHas('success', 'Corrida cancelada.');
    ExecuteMigrationRun::dispatchSync($run);

    expect($run->refresh()->status)->toBe(MigrationRunStatus::Cancelled)
        ->and($run->finished_at)->not->toBeNull()
        ->and($step->refresh()->status)->toBe(MigrationRunStatus::Pending);
});

test('a finished run cannot be cancelled', function () {
    $run = MigrationRun::factory()->create(['status' => MigrationRunStatus::Completed]);

    $this->post(route('central.migrations.runs.cancel', $run))->assertSessionHasErrors(['run' => 'La corrida ya terminó.']);

    expect($run->refresh()->status)->toBe(MigrationRunStatus::Completed);
});

test('warns when a pending run has not been picked up by a queue worker', function () {
    $run = MigrationRun::factory()->create();

    $this->get(route('central.migrations.runs.show', $run))
        ->assertInertia(fn (Assert $page) => $page->where('run.waiting_for_worker', false));

    $this->travel(16)->seconds();

    $this->get(route('central.migrations.runs.show', $run))
        ->assertInertia(fn (Assert $page) => $page->where('run.waiting_for_worker', true));
});

test('explains why a pending run has not started from the queue and the worker heartbeat', function () {
    config(['queue.default' => 'database']);
    $first = MigrationRun::factory()->create();
    $run = MigrationRun::factory()->create();
    ExecuteMigrationRun::dispatch($first);
    ExecuteMigrationRun::dispatch($run);
    $this->travel(1)->minute();
    $reason = fn () => $this->get(route('central.migrations.runs.show', $run))->viewData('page')['props']['run']['queue_status'];

    expect($reason())->toMatchArray(['reason_code' => 'no_worker', 'worker_active' => false, 'jobs_ahead' => 1, 'connection' => 'database'])
        ->and($reason()['reason'])->toBe('No hay ningún worker procesando la cola: nunca se ha visto uno en este servidor.');

    Cache::put(MigrationRunQueueStatus::HEARTBEAT_KEY, now()->subSeconds(5)->toIso8601String());
    expect($reason())->toMatchArray(['reason_code' => 'busy', 'worker_active' => true]);

    DB::table('jobs')->orderBy('id')->limit(1)->delete();
    expect($reason()['reason_code'])->toBe('should_start');

    DB::table('jobs')->update(['reserved_at' => now()->subMinute()->timestamp]);
    expect($reason()['reason_code'])->toBe('reserved');

    $payload = DB::table('jobs')->value('payload');
    DB::table('jobs')->delete();
    expect($reason()['reason_code'])->toBe('missing');

    DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => $payload, 'exception' => "RuntimeException: Sin memoria\n#0 trace", 'failed_at' => now()]);
    expect($reason())->toMatchArray(['reason_code' => 'failed', 'failed_error' => 'RuntimeException: Sin memoria']);
});

test('the queue status is only computed while the run waits for a worker', function () {
    $run = MigrationRun::factory()->create(['status' => MigrationRunStatus::Running]);
    $this->travel(1)->minute();

    $this->get(route('central.migrations.runs.show', $run))->assertInertia(fn (Assert $page) => $page->where('run.queue_status', null));
});

test('the run form lists sources, tenants and modules with their dependencies', function () {
    $this->get(route('central.migrations.runs.create'))->assertInertia(fn (Assert $page) => $page
        ->component('central/migrations/runs/Create')
        ->where('sources.0.value', $this->source->id)
        ->where('tenants.0.value', $this->tenant->id)
        ->where('modules.7', ['value' => 'users', 'label' => 'Usuarios', 'depends_on' => ['roles']])
    );
});
