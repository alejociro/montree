<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_itineraries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('step_number');
            $table->string('title');
            $table->text('description');
            $table->string('duration_label')->nullable();
            $table->timestamps();

            $table->unique(['tour_id', 'step_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_itineraries');
    }
};
