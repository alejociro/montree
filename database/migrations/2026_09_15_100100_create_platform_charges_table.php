<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('base_amount', 12, 2);
            $table->string('commission_type', 20);
            $table->decimal('applied_value', 12, 2);
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->timestamp('charged_at');
            $table->timestamps();

            $table->index(['tenant_id', 'charged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_charges');
    }
};
