<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table): void {
            $table->foreignId('tour_id')->after('tenant_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_default')->after('tour_id')->default(false);

            $table->index(['tenant_id', 'tour_id']);
        });
    }

    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'tour_id']);
            $table->dropConstrainedForeignId('tour_id');
            $table->dropColumn('is_default');
        });
    }
};
