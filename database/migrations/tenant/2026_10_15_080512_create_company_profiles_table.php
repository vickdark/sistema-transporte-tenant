<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configuración de la empresa para sus documentos (FUEC): contacto, tipo de servicio, datos de
     * habilitación, logo y firma. Una sola fila por schema. La razón social y el NIT no están
     * aquí: son la identidad del inquilino y viven en `tenants` (panel central).
     */
    public function up(): void
    {
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('address')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('service_type', 150)->default('Transporte especial de pasajeros');
            // Prefijo del número del FUEC: código de la dirección territorial, resolución y año de habilitación.
            $table->string('territorial_code', 3)->nullable();
            $table->string('habilitation_resolution', 4)->nullable();
            $table->string('habilitation_year', 2)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('signature_path')->nullable();
            $table->string('legacy_source', 50)->nullable();
            $table->string('legacy_id', 50)->nullable();
            $table->timestamps();

            $table->unique(['legacy_source', 'legacy_id']);
        });

        DB::statement('ALTER TABLE company_profiles ADD CONSTRAINT company_profiles_single_row CHECK (id = 1)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_profiles');
    }
};
