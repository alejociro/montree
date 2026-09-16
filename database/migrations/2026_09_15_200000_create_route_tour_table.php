<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_tour', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_id')->constrained()->restrictOnDelete();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['tour_id', 'route_id']);
            $table->index(['tour_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_tour');
    }
};
