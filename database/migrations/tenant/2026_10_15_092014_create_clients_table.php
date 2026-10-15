<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clientes de la empresa o de un aliado (`ally_id`: el dueño; null = la empresa). Como en el
     * legacy, cada usuario ve solo los clientes de su dueño.
     *
     * - La identificación no lleva índice único: producción tiene repetidos (sucursales y
     *   duplicados) que se migran tal cual; la regla de no repetir se valida al crear y editar.
     * - `users.client_id`: la cuenta del portal de un cliente. Un usuario pertenece como mucho a
     *   un actor (aliado, proveedor o cliente; empleados se suman en 2.7).
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('identification_type', 20);
            $table->string('identification', 20)->index();
            $table->string('check_digit', 2)->nullable();
            $table->string('branch_code', 20)->nullable();
            $table->string('first_names', 100)->nullable();
            $table->string('last_names', 100)->nullable();
            $table->string('business_name', 200)->nullable();
            $table->string('trade_name', 200)->nullable();
            $table->string('city', 100);
            $table->string('address', 200);
            $table->string('phone', 20);
            $table->string('extension', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('ally_id')->nullable()->constrained('allies')->nullOnDelete();
            $table->string('legacy_source', 50)->nullable();
            $table->string('legacy_id', 50)->nullable();
            $table->timestamps();

            $table->index(['ally_id', 'is_active']);
            $table->unique(['legacy_source', 'legacy_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
        });

        DB::statement('ALTER TABLE users DROP CONSTRAINT users_at_most_one_actor');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_at_most_one_actor CHECK (num_nonnulls(ally_id, supplier_id, client_id) <= 1)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_at_most_one_actor');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_at_most_one_actor CHECK (num_nonnulls(ally_id, supplier_id) <= 1)');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });

        Schema::dropIfExists('clients');
    }
};
