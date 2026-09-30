<?php

declare(strict_types=1);

use App\Enums\TourDateStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            // WHY: la salida puede crearse sin guía y asignarse después; la FK
            // es `restrictOnDelete()` (no `nullOnDelete()`) porque quitarle el
            // guía a una salida es un acto explícito del operador, no algo
            // que deba pasar en cascada al borrar un usuario.
            $table->foreignId('guide_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('capacity');
            $table->unsignedInteger('booked_count')->default(0);
            $table->decimal('price_override', 12, 2)->nullable();
            $table->unsignedTinyInteger('min_payment_pct')->nullable();
            $table->string('status')->default(TourDateStatus::Open->value);
            $table->text('notes')->nullable();
            // WHY: la salida hereda del producto (T7): `null` = hereda y sigue
            // los cambios futuros del tour; solo lleva valor cuando el
            // operador personaliza esta salida en concreto.
            $table->json('itinerary')->nullable();
            $table->json('includes')->nullable();
            $table->json('excludes')->nullable();
            $table->json('requirements')->nullable();
            $table->string('meeting_point')->nullable();
            // WHY: fecha límite para reservar esta salida; `null` = se puede
            // reservar hasta que empiece (comportamiento por defecto).
            $table->dateTime('booking_closes_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'starts_at']);
            $table->index(['tour_id', 'starts_at']);
            $table->index(['tour_id', 'status']);
            $table->index('guide_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_dates');
    }
};
