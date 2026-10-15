<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Referencia al registro legacy del que vino cada fila (tabla + id), para que repetir una
     * corrida de migración actualice en vez de duplicar. Ver PLAN_MIGRACION.md §5.3.
     */
    public function up(): void
    {
        foreach (['users', 'roles'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('legacy_source', 50)->nullable();
                $table->string('legacy_id', 50)->nullable();

                $table->unique(['legacy_source', 'legacy_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['users', 'roles'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique(['legacy_source', 'legacy_id']);
                $table->dropColumn(['legacy_source', 'legacy_id']);
            });
        }
    }
};
