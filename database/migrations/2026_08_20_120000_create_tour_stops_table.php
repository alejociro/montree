<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('kind');
            // Glifo del pin (A / 1..n / B). Lo deriva SyncTourStopsAction del orden.
            $table->string('code', 4);
            $table->string('label', 40)->nullable();
            $table->string('name', 120);
            $table->string('place', 120)->nullable();
            $table->string('time_label', 30)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            // WHY: el itinerario se borra y recrea en cada guardado, así que la parada
            // se ata al número de paso, no al id de `tour_itineraries`.
            $table->unsignedInteger('itinerary_step')->nullable();
            $table->timestamps();

            $table->unique(['tour_id', 'position']);
            $table->index(['tenant_id', 'tour_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_stops');
    }
};
