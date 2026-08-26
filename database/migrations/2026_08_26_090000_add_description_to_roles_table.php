<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una línea que explica para qué sirve un rol propio de la agencia.
 *
 * WHY: el rediseño de «Roles y permisos» muestra la descripción en la tarjeta,
 * en el panel de permisos y en el modal de creación, porque un nombre suelto
 * («Coordinación») no dice cuándo usar el rol. Los 6 roles base no la guardan
 * acá —son filas globales, iguales para todas las agencias— sino en
 * `UserRole::description()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->table(), function (Blueprint $table): void {
            $table->string('description', 200)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table($this->table(), function (Blueprint $table): void {
            $table->dropColumn('description');
        });
    }

    private function table(): string
    {
        /** @var string $table */
        $table = config('permission.table_names.roles', 'roles');

        return $table;
    }
};
