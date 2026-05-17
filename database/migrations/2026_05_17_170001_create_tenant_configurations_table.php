<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('primary_color', 7)->default('#0ea5e9');
            $table->string('secondary_color', 7)->default('#64748b');
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('hero_image_path')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('timezone', 64)->default('UTC');
            $table->string('locale', 8)->default('en');
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();
            $table->json('social_links')->nullable();
            $table->json('contact_info')->nullable();
            $table->longText('custom_css')->nullable();
            $table->boolean('reviews_require_moderation')->default(true);
            $table->boolean('require_traveler_details')->default(true);
            $table->unsignedSmallInteger('booking_advance_hours')->default(24);
            $table->unsignedSmallInteger('booking_expiration_minutes')->default(30);
            $table->unsignedTinyInteger('min_partial_payment_pct')->default(30);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_configurations');
    }
};
