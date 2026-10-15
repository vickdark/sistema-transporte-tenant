<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - roles.base_key: identifica los roles sembrados (App\Enums\BaseRole) aunque la empresa los renombre.
     * - roles.description: texto libre para explicar el rol en la interfaz.
     * - roles.is_protected: el rol Administrador; tiene todos los permisos y no se borra ni se edita.
     * - Un rol por usuario: índice único en model_has_roles. Para permitir varios roles más
     *   adelante basta con quitar este índice (el servicio AssignUserRole también lo garantiza).
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('base_key', 50)->nullable()->unique()->after('guard_name');
            $table->string('description')->nullable()->after('base_key');
            $table->boolean('is_protected')->default(false)->after('description');
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->unique(['model_id', 'model_type'], 'model_has_roles_one_role_per_model_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropUnique('model_has_roles_one_role_per_model_unique');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['base_key']);
            $table->dropColumn(['base_key', 'description', 'is_protected']);
        });
    }
};
