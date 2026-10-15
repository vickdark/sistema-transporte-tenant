<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Acceso SFTP (cifrado) a la carpeta del legacy en su servidor, la que contiene `uploads/`:
     * servidor, puerto, usuario, contraseña o llave privada y carpeta. Las rutas guardadas en la
     * base legacy se resuelven contra esa carpeta.
     */
    public function up(): void
    {
        Schema::table('migration_sources', function (Blueprint $table) {
            $table->text('files_connection')->nullable()->after('credentials');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('migration_sources', function (Blueprint $table) {
            $table->dropColumn('files_connection');
        });
    }
};
