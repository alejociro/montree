<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\PaymentType;
use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $travelers = fake()->numberBetween(1, 5);
        $unitPrice = fake()->randomFloat(2, 50, 500);
        $subtotal = round($unitPrice * $travelers, 2);

        return [
            'user_id' => User::factory(),
            'tour_id' => Tour::factory(),
            'tour_date_id' => TourDate::factory(),
            'promotion_id' => null,
            'travelers_count' => $travelers,
            'subtotal' => $subtotal,
            'discount_amount' => 0,
            'total_amount' => $subtotal,
            'paid_amount' => 0,
            'currency' => 'USD',
            'status' => BookingStatus::PendingPayment,
            'payment_type' => PaymentType::Full,
            'special_requests' => null,
            'contact_snapshot' => [
                'name' => fake()->name(),
                'email' => fake()->safeEmail(),
                'phone' => fake()->phoneNumber(),
            ],
            'expires_at' => now()->addMinutes(30),
            'confirmed_at' => null,
            'cancelled_at' => null,
            'completed_at' => null,
            'cancellation_reason' => null,
        ];
    }

    public function confirmed(): self
    {
        return $this->state(fn (array $attrs) => [
            'status' => BookingStatus::Confirmed,
            'paid_amount' => $attrs['total_amount'] ?? 0,
            'confirmed_at' => now(),
            'expires_at' => null,
        ]);
    }

    public function completed(): self
    {
        return $this->state(fn (array $attrs) => [
            'status' => BookingStatus::Completed,
            'paid_amount' => $attrs['total_amount'] ?? 0,
            'confirmed_at' => now()->subDays(7),
            'completed_at' => now()->subDay(),
            'expires_at' => null,
        ]);
    }

    public function cancelled(): self
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => fake()->sentence(),
        ]);
    }

    public function expired(): self
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Expired,
            'expires_at' => now()->subHour(),
        ]);
    }
}
