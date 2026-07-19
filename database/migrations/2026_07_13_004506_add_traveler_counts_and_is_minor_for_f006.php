<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->unsignedInteger('adults_count')->after('travelers_count');
            $table->unsignedInteger('minors_count')->default(0)->after('adults_count');
        });

        Schema::table('booking_travelers', function (Blueprint $table): void {
            $table->boolean('is_minor')->default(false)->after('full_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['adults_count', 'minors_count']);
        });

        Schema::table('booking_travelers', function (Blueprint $table): void {
            $table->dropColumn('is_minor');
        });
    }
};
