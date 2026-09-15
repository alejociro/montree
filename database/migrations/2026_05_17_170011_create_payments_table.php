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
            $table->string('gateway')->default(PaymentGateway::PlaceToPay->value);
            $table->string('request_id')->nullable();
            $table->string('internal_reference')->nullable();
            $table->string('reference')->nullable();
            $table->string('process_url', 512)->nullable();
            $table->timestamp('session_expires_at')->nullable();
            $table->string('gateway_status', 32)->nullable();
            $table->string('authorization')->nullable();
            $table->string('receipt')->nullable();
            $table->string('franchise')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_method_name')->nullable();
            $table->string('issuer_name')->nullable();
            $table->json('processor_fields')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('type')->default(PaymentType::Full->value);
            $table->string('status')->default(PaymentStatus::Pending->value);
            $table->string('status_message')->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'gateway', 'request_id']);
            $table->index('reference');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'gateway_status'], 'payments_tenant_gateway_status_index');
            $table->index(['tenant_id', 'gateway', 'status'], 'payments_tenant_gateway_domain_status_index');
            $table->index(['tenant_id', 'processed_at']);
            $table->index(['booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
