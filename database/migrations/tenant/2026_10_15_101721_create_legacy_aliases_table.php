<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registros legacy repetidos que la migración unificó en uno solo: el id legacy del
     * duplicado apunta al registro que sí se importó. Los importadores que vienen después (p. ej.
     * órdenes → rutas) resuelven aquí las referencias a los duplicados.
     */
    public function up(): void
    {
        Schema::create('legacy_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('legacy_source', 50);
            $table->string('legacy_id', 50);
            $table->unsignedBigInteger('target_id');
            $table->timestamps();

            $table->unique(['legacy_source', 'legacy_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_aliases');
    }
};
