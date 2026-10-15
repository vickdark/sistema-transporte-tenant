<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aliados y proveedores: terceros de la empresa (no inquilinos), con los mismos datos.
     *
     * - Una misma persona o empresa puede registrarse como aliado y como proveedor y luego
     *   vincularse ("mixto"). El vínculo se guarda en un solo lado (`suppliers.ally_id`, único),
     *   así nunca queda a medias; en el legacy eran dos columnas cruzadas mantenidas a mano.
     * - Nombre y NIT no llevan índice único: la producción tiene proveedores repetidos que se
     *   migran tal cual para depurarlos; la regla de no repetir se valida al crear y editar.
     * - `users.ally_id` / `users.supplier_id`: el usuario de un aliado o proveedor (como mucho uno).
     *   La regla "exactamente un actor" se completa cuando existan empleados y clientes.
     */
    public function up(): void
    {
        Schema::create('allies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('nit', 50)->nullable()->index();
            $table->string('phone', 50)->nullable();
            $table->string('address')->nullable();
            $table->string('email')->nullable();
            $table->string('legacy_source', 50)->nullable();
            $table->string('legacy_id', 50)->nullable();
            $table->timestamps();

            $table->index('name');
            $table->unique(['legacy_source', 'legacy_id']);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('nit', 50)->nullable()->index();
            $table->string('phone', 50)->nullable();
            $table->string('address')->nullable();
            $table->string('email')->nullable();
            $table->foreignId('ally_id')->nullable()->unique()->constrained('allies')->nullOnDelete();
            $table->string('legacy_source', 50)->nullable();
            $table->string('legacy_id', 50)->nullable();
            $table->timestamps();

            $table->index('name');
            $table->unique(['legacy_source', 'legacy_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('ally_id')->nullable()->constrained('allies')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
        });

        DB::statement('ALTER TABLE users ADD CONSTRAINT users_at_most_one_actor CHECK (num_nonnulls(ally_id, supplier_id) <= 1)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_at_most_one_actor');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropConstrainedForeignId('ally_id');
        });

        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('allies');
    }
};
