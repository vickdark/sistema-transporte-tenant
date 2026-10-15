<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rutas (trayectos) que usan las órdenes de servicio. El nombre no se guarda: es siempre
     * "Inicio - Fin". No se eliminan (las órdenes las referencian): se inactivan.
     *
     * Inicio y fin no llevan índice único: producción tiene rutas repetidas que se migran tal
     * cual; la regla de no repetir (sin distinguir mayúsculas ni espacios) se valida al guardar.
     */
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->string('start_point', 100);
            $table->string('end_point', 100);
            $table->text('description')->nullable();
            $table->string('type', 20);
            $table->string('status', 20)->index();
            $table->string('legacy_source', 50)->nullable();
            $table->string('legacy_id', 50)->nullable();
            $table->timestamps();

            $table->index(['start_point', 'end_point']);
            $table->unique(['legacy_source', 'legacy_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routes');
    }
};
