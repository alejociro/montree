<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // WHY: la pantalla de equipo (F018, fase 3) muestra "último acceso" por usuario.
            // Nullable y SIN backfill: las cuentas existentes nunca fueron medidas, y copiar
            // `created_at` mostraría una fecha de acceso falsa. NULL == "nunca / sin registro".
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_login_at');
        });
    }
};
