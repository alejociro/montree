<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_dates', function (Blueprint $table) {
            $table->foreignId('route_id')->nullable()->after('guide_id')->constrained()->restrictOnDelete();
            $table->foreignId('provider_id')->nullable()->after('route_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tour_dates', function (Blueprint $table) {
            $table->dropForeign(['route_id']);
            $table->dropForeign(['provider_id']);
            $table->dropColumn(['route_id', 'provider_id']);
        });
    }
};
