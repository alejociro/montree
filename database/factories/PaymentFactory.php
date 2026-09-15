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
            'gateway' => PaymentGateway::PlaceToPay,
            'request_id' => (string) fake()->unique()->numberBetween(100000, 999999),
            'reference' => 'MTR-'.fake()->unique()->numberBetween(1, 99999),
            'internal_reference' => null,
            'amount' => fake()->randomFloat(2, 50, 1500),
            'currency' => 'USD',
            'type' => PaymentType::Full,
            'status' => PaymentStatus::Pending,
            'gateway_status' => null,
            'status_message' => null,
            'gateway_response' => null,
            'processed_at' => null,
        ];
    }

    public function completed(): self
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Completed,
            'gateway_status' => 'APPROVED',
            'status_message' => 'Aprobada',
            'processed_at' => now(),
            'internal_reference' => (string) fake()->unique()->numberBetween(1000000000, 9999999999),
            'authorization' => (string) fake()->numberBetween(100000, 999999),
            'receipt' => (string) fake()->numberBetween(100000000, 999999999),
            'franchise' => 'CR_VS',
            'payment_method' => 'visa',
            'payment_method_name' => 'Visa',
            'issuer_name' => 'BANCOLOMBIA',
        ]);
    }

    public function processing(): self
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Processing,
            'gateway_status' => 'OK',
            'process_url' => 'https://checkout.test/session/'.Str::random(12),
            'session_expires_at' => now()->addMinutes(30),
        ]);
    }

    public function failed(): self
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Failed,
            'gateway_status' => 'REJECTED',
            'status_message' => 'Rechazada por el banco',
        ]);
    }
}
