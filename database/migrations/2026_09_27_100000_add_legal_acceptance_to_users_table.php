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
        // WHY: Ley 1581 de 2012 (art. 9) obliga a conservar prueba de la
        // autorización para tratar datos personales. Null = no hay prueba, no
        // "no aceptó": las cuentas anteriores a este cambio quedan en null.
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable()->after('password_set_at');
            $table->timestamp('data_policy_accepted_at')->nullable()->after('terms_accepted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['terms_accepted_at', 'data_policy_accepted_at']);
        });
    }
};
