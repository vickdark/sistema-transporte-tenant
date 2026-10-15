<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vehículos (legacy: módulo `vehiculos`) con sus documentos, fotos, partes y convenios.
     *
     * - Dueño: la empresa (sin `ally_id` ni `supplier_id`), un proveedor, un aliado o ambos cuando
     *   el tercero es mixto (como en el legacy; `LinkAllyAndSupplier` los mantiene al convertir y
     *   revertir). Los vehículos no se eliminan: se inhabilitan.
     * - Convenio: empresa con la que el vehículo opera en convenio (sale en el FUEC); como mucho uno.
     * - Documentos: uno vigente por tipo (`replaced_at` null); al renovar, el anterior queda en el
     *   historial con su archivo.
     * - Partes: catálogo (`parts`, en el legacy era un ENUM que se alteraba en caliente) y las de
     *   cada vehículo con el kilometraje entre mantenimientos.
     */
    public function up(): void
    {
        Schema::create('agreements', function (Blueprint $table) {
            $table->id();
            $table->string('nit', 20)->unique();
            $table->string('business_name', 255);
            $table->string('legacy_source', 50)->nullable();
            $table->string('legacy_id', 50)->nullable();
            $table->timestamps();

            $table->unique(['legacy_source', 'legacy_id']);
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('plate', 10)->unique();
            $table->string('internal_number', 50)->nullable();
            $table->decimal('current_mileage', 10, 2)->default(0);
            $table->date('registration_date');
            $table->string('registration_place', 100);
            $table->smallInteger('model_year');
            $table->string('brand', 50);
            $table->string('line', 50);
            $table->string('vehicle_class', 30);
            $table->string('engine_number', 50);
            $table->string('chassis_number', 50);
            $table->string('color', 50);
            $table->unsignedInteger('displacement')->nullable();
            $table->string('fuel_type', 20)->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('ally_id')->nullable()->constrained('allies')->nullOnDelete();
            $table->foreignId('agreement_id')->nullable()->constrained('agreements')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->string('legacy_source', 50)->nullable();
            $table->string('legacy_id', 50)->nullable();
            $table->timestamps();

            $table->index('supplier_id');
            $table->index('ally_id');
            $table->unique(['legacy_source', 'legacy_id']);
        });

        Schema::create('vehicle_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('number', 50)->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable()->index();
            $table->string('file_path')->nullable();
            $table->timestamp('replaced_at')->nullable();
            $table->string('legacy_source', 50)->nullable();
            $table->string('legacy_id', 50)->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'type']);
            $table->unique(['legacy_source', 'legacy_id']);
        });

        // Un solo documento vigente por tipo; los reemplazados quedan como historial.
        DB::statement('CREATE UNIQUE INDEX vehicle_documents_current_unique ON vehicle_documents (vehicle_id, type) WHERE replaced_at IS NULL');

        Schema::create('vehicle_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('legacy_source', 50)->nullable();
            $table->string('legacy_id', 50)->nullable();
            $table->timestamps();

            $table->unique(['legacy_source', 'legacy_id']);
        });

        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->timestamps();
        });

        // El nombre no se repite sin distinguir mayúsculas ni espacios.
        DB::statement("CREATE UNIQUE INDEX parts_name_unique ON parts (lower(regexp_replace(trim(name), '\\s+', ' ', 'g')))");

        Schema::create('vehicle_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('maintenance_interval_km');
            $table->string('legacy_source', 50)->nullable();
            $table->string('legacy_id', 50)->nullable();
            $table->timestamps();

            $table->unique(['vehicle_id', 'part_id']);
            $table->unique(['legacy_source', 'legacy_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_parts');
        Schema::dropIfExists('parts');
        Schema::dropIfExists('vehicle_photos');
        Schema::dropIfExists('vehicle_documents');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('agreements');
    }
};
