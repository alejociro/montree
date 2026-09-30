<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_schedules', function (Blueprint $table): void {
            $table->id();
            // WHY: `null` es el esquema GLOBAL (uno solo, lo garantiza la app,
            // no un índice: SQLite/MySQL no tratan dos NULL como duplicados).
            $table->foreignId('tenant_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->char('currency', 3);
            $table->json('tiers');
            // WHY: tope único del ESQUEMA (no del rango): cobro = min(base ×
            // rate / 100, max_charge) sin importar en qué rango cae la
            // reserva. `null` = sin tope.
            $table->decimal('max_charge', 12, 2)->nullable();
            $table->timestamps();
        });

        // Esquema global por defecto: el mismo ejemplo de la especificación,
        // así el producto arranca con una tarifa razonable en vez de sin cobro.
        DB::table('commission_schedules')->insert([
            'tenant_id' => null,
            'currency' => 'COP',
            'tiers' => json_encode([
                ['from' => '0.00', 'to' => '500000.00', 'rate' => '9.00'],
                ['from' => '500000.00', 'to' => '1500000.00', 'rate' => '7.00'],
                ['from' => '1500000.00', 'to' => null, 'rate' => '5.00'],
            ]),
            'max_charge' => '300000.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_schedules');
    }
};
