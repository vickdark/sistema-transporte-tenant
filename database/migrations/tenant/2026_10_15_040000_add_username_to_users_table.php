<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Login "usuario o correo": el usuario es obligatorio y único; el correo pasa a ser opcional
     * (sigue siendo único). A los usuarios existentes se les asigna un usuario a partir del correo.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->after('name');
        });

        $taken = [];

        foreach (DB::table('users')->orderBy('id')->get(['id', 'email']) as $user) {
            $base = Str::of((string) $user->email)->before('@')->lower()->replaceMatches('/[^a-z0-9._-]/', '')->limit(40, '')->value() ?: 'usuario';
            $username = $base;

            for ($suffix = 2; in_array($username, $taken, true); $suffix++) {
                $username = "{$base}{$suffix}";
            }

            $taken[] = $username;
            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable(false)->unique()->change();
            $table->string('email')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
            $table->string('email')->nullable(false)->change();
        });
    }
};
