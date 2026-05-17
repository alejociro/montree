<?php

declare(strict_types=1);

use App\Enums\TourDifficulty;
use App\Enums\TourStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('short_description', 280)->nullable();
            $table->longText('description');
            $table->unsignedInteger('duration_hours');
            $table->string('difficulty')->default(TourDifficulty::Easy->value);
            $table->decimal('base_price', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('default_capacity');
            $table->string('meeting_point')->nullable();
            $table->decimal('meeting_latitude', 10, 7)->nullable();
            $table->decimal('meeting_longitude', 10, 7)->nullable();
            $table->json('includes')->nullable();
            $table->json('excludes')->nullable();
            $table->json('requirements')->nullable();
            $table->string('status')->default(TourStatus::Draft->value);
            $table->decimal('rating_average', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'category_id']);
            $table->index(['tenant_id', 'difficulty']);
            $table->index(['tenant_id', 'base_price']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tours');
    }
};
