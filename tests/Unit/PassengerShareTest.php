<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\BookingStatus;
use App\Models\Booking;
use PHPUnit\Framework\TestCase;

/**
 * Reparto del dinero por pasajero (D5): se calcula, no se guarda.
 */
final class PassengerShareTest extends TestCase
{
    public function test_the_share_splits_the_booking_evenly(): void
    {
        $share = $this->booking('400000.00', '400000.00', 2)->passengerShare();

        $this->assertSame('200000.00', $share['share_amount']);
        $this->assertSame('200000.00', $share['paid_amount']);
        $this->assertSame('0.00', $share['due_amount']);
        $this->assertSame('paid', $share['status']);
    }

    public function test_an_odd_number_of_travelers_rounds_to_two_decimals(): void
    {
        $share = $this->booking('100.00', '0.00', 3)->passengerShare();

        $this->assertSame('33.33', $share['share_amount']);
        $this->assertSame('33.33', $share['due_amount']);
        $this->assertSame('due', $share['status']);
    }

    public function test_a_partial_payment_leaves_every_passenger_with_the_same_balance(): void
    {
        $share = $this->booking('300000.00', '100000.00', 2)->passengerShare();

        $this->assertSame('150000.00', $share['share_amount']);
        $this->assertSame('50000.00', $share['paid_amount']);
        $this->assertSame('100000.00', $share['due_amount']);
    }

    public function test_a_booking_without_travelers_does_not_divide_by_zero(): void
    {
        $share = $this->booking('120000.00', '0.00', 0)->passengerShare();

        $this->assertSame('120000.00', $share['share_amount']);
    }

    public function test_the_due_amount_of_the_booking_is_the_difference(): void
    {
        $this->assertSame('200000.00', $this->booking('300000.00', '100000.00', 2)->due_amount);
    }

    private function booking(string $total, string $paid, int $travelers): Booking
    {
        return new Booking([
            'total_amount' => $total,
            'paid_amount' => $paid,
            'travelers_count' => $travelers,
            'currency' => 'COP',
            'status' => BookingStatus::Confirmed,
        ]);
    }
}
