<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tablas de control (centrales) del módulo de migración de datos legacy. Ver PLAN_MIGRACION.md §5.3.
     */
    public function up(): void
    {
        Schema::create('migration_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 30);
            $table->text('credentials')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('central_users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('migration_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('migration_source_id')->constrained()->restrictOnDelete();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('mode', 20);
            $table->string('status', 20)->index();
            $table->json('modules');
            $table->foreignId('started_by')->nullable()->constrained('central_users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('migration_run_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('migration_run_id')->constrained()->cascadeOnDelete();
            $table->string('module', 50);
            $table->string('status', 20);
            $table->unsignedInteger('read_count')->default(0);
            $table->unsignedInteger('inserted_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('quarantined_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['migration_run_id', 'module']);
        });

        Schema::create('migration_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('migration_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('migration_run_step_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('severity', 20);
            $table->string('legacy_table', 100)->nullable();
            $table->string('legacy_id', 100)->nullable();
            $table->text('message');
            $table->json('payload')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['migration_run_id', 'severity']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('migration_issues');
        Schema::dropIfExists('migration_run_steps');
        Schema::dropIfExists('migration_runs');
        Schema::dropIfExists('migration_sources');
    }
};
