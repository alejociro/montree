<?php

declare(strict_types=1);

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('gateway')->default(PaymentGateway::Stripe->value);
            $table->string('gateway_payment_id')->nullable();
            $table->string('gateway_charge_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('type')->default(PaymentType::Full->value);
            $table->string('status')->default(PaymentStatus::Pending->value);
            $table->string('failure_reason')->nullable();
            $table->json('gateway_response')->nullable();
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->string('refund_reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'gateway_payment_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
