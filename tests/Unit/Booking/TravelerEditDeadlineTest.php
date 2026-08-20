<?php

declare(strict_types=1);

namespace Tests\Unit\Booking;

use App\Models\Booking;
use App\Models\TourDate;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * D10 — el borde de la ventana, sin HTTP: una reserva cuya salida no se puede
 * resolver no bloquea a nadie.
 */
final class TravelerEditDeadlineTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_booking_without_a_departure_has_no_deadline_and_never_closes(): void
    {
        $booking = new Booking;
        $booking->setRelation('tourDate', null);

        $this->assertNull($booking->travelerEditDeadline());
        $this->assertFalse($booking->isTravelerEditWindowClosed());
    }

    public function test_the_deadline_is_the_departure_minus_the_configured_window(): void
    {
        config(['montree.passengers.traveler_edit_cutoff_hours' => 48]);

        $booking = new Booking;
        $booking->setRelation('tourDate', new TourDate(['starts_at' => Carbon::parse('2026-09-14 06:00:00')]));

        $this->assertSame(
            '2026-09-12 06:00:00',
            $booking->travelerEditDeadline()?->format('Y-m-d H:i:s'),
        );
    }
}
