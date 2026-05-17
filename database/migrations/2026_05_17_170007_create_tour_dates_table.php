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
            $table->foreignId('guide_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('capacity');
            $table->unsignedInteger('booked_count')->default(0);
            $table->decimal('price_override', 12, 2)->nullable();
            $table->string('status')->default(TourDateStatus::Open->value);
            $table->text('notes')->nullable();
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
