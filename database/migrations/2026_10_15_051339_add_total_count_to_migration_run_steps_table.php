<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registros que el paso va a leer de la fuente, para mostrar su progreso en el panel.
     */
    public function up(): void
    {
        Schema::table('migration_run_steps', function (Blueprint $table) {
            $table->unsignedInteger('total_count')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('migration_run_steps', function (Blueprint $table) {
            $table->dropColumn('total_count');
        });
    }
};
