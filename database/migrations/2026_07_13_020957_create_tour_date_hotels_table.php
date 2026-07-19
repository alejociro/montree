<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_date_hotels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_date_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tour_date_id', 'hotel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_date_hotels');
    }
};
