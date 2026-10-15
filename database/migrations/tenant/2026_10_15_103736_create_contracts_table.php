<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contratos de transporte con un cliente. Dos clases que el legacy tenía en tablas separadas
     * y que siguen siendo módulos distintos: los propios (`kind = own`, de clientes de la empresa)
     * y los de aliados (`kind = ally`, de clientes de un aliado; `ally_id` = el aliado).
     *
     * - `number`: número del contrato que va en el FUEC, consecutivo por clase. Los migrados
     *   conservan el id legacy y los nuevos siguen desde el último, para no repetir números.
     * - Datos del responsable: obligatorios al guardar desde la pantalla; los migrados pueden
     *   no tenerlos.
     * - No se eliminan (las órdenes y el FUEC los referencian).
     */
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10);
            $table->unsignedInteger('number');
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('ally_id')->nullable()->constrained('allies')->restrictOnDelete();
            $table->string('purpose', 30);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('responsible_identification', 20)->nullable();
            $table->string('responsible_name', 150)->nullable();
            $table->string('responsible_email', 100)->nullable();
            $table->string('responsible_phone', 20)->nullable();
            $table->string('legacy_source', 50)->nullable();
            $table->string('legacy_id', 50)->nullable();
            $table->timestamps();

            $table->unique(['kind', 'number']);
            $table->index(['client_id', 'kind', 'starts_on']);
            $table->unique(['legacy_source', 'legacy_id']);
        });

        DB::statement("ALTER TABLE contracts ADD CONSTRAINT contracts_kind_owner CHECK ((kind = 'own' AND ally_id IS NULL) OR (kind = 'ally' AND ally_id IS NOT NULL))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
