<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'gateway' => PaymentGateway::Stripe,
            'gateway_payment_id' => 'pi_'.Str::random(24),
            'gateway_charge_id' => null,
            'amount' => fake()->randomFloat(2, 50, 1500),
            'currency' => 'USD',
            'type' => PaymentType::Full,
            'status' => PaymentStatus::Pending,
            'failure_reason' => null,
            'gateway_response' => null,
            'refunded_amount' => 0,
            'refund_reason' => null,
            'processed_at' => null,
            'refunded_at' => null,
        ];
    }

    public function completed(): self
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Completed,
            'processed_at' => now(),
            'gateway_charge_id' => 'ch_'.Str::random(24),
        ]);
    }

    public function failed(): self
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Failed,
            'failure_reason' => 'card_declined',
        ]);
    }
}
