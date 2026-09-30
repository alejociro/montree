<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('base_amount', 12, 2);
            // WHY: el % aplicado sobre TODO el valor de la reserva (no
            // escalonado), del rango del esquema en el que cayó.
            $table->decimal('applied_rate', 12, 2);
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->decimal('tier_from', 12, 2)->nullable();
            $table->decimal('tier_to', 12, 2)->nullable();
            // WHY: el tope del ESQUEMA vigente al momento del cobro (no el
            // tope del rango: eso ya no existe). `null` = sin tope.
            $table->decimal('max_charge', 12, 2)->nullable();
            $table->boolean('was_capped')->default(false);
            $table->string('schedule_scope', 10)->nullable();
            $table->timestamp('charged_at');
            $table->timestamps();

            $table->index(['tenant_id', 'charged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_charges');
    }
};
