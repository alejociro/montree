<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Enums\BookingStatus;
use App\Enums\TourDateStatus;
use App\Jobs\ExpirePendingBookingsJob;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ExpirePendingBookingsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_expires_pending_bookings_past_expiration(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->makeCurrent();

        $user = User::factory()->customerOf($tenant)->create();
        $tour = Tour::factory()->for($tenant)->create();
        $tourDate = TourDate::factory()->for($tour)->create([
            'booked_count' => 3,
            'capacity' => 10,
            'status' => TourDateStatus::Open,
        ]);

        $booking = Booking::factory()->for($tour)->for($tourDate)->for($user)->create([
            'status' => BookingStatus::PendingPayment,
            'expires_at' => now()->subMinutes(5),
            'travelers_count' => 3,
        ]);

        (new ExpirePendingBookingsJob)->handle();

        $booking->refresh();
        $tourDate->refresh();

        $this->assertEquals(BookingStatus::Expired, $booking->status);
        $this->assertEquals(0, $tourDate->booked_count);
    }

    public function test_does_not_expire_bookings_not_yet_expired(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->makeCurrent();

        $user = User::factory()->customerOf($tenant)->create();
        $tour = Tour::factory()->for($tenant)->create();
        $tourDate = TourDate::factory()->for($tour)->create([
            'booked_count' => 2,
            'capacity' => 10,
        ]);

        $booking = Booking::factory()->for($tour)->for($tourDate)->for($user)->create([
            'status' => BookingStatus::PendingPayment,
            'expires_at' => now()->addHour(),
            'travelers_count' => 2,
        ]);

        (new ExpirePendingBookingsJob)->handle();

        $booking->refresh();
        $this->assertEquals(BookingStatus::PendingPayment, $booking->status);
    }

    public function test_reopens_full_tour_date_when_booking_expires(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->makeCurrent();

        $user = User::factory()->customerOf($tenant)->create();
        $tour = Tour::factory()->for($tenant)->create();
        $tourDate = TourDate::factory()->for($tour)->create([
            'booked_count' => 10,
            'capacity' => 10,
            'status' => TourDateStatus::Full,
        ]);

        $booking = Booking::factory()->for($tour)->for($tourDate)->for($user)->create([
            'status' => BookingStatus::PendingPayment,
            'expires_at' => now()->subMinutes(1),
            'travelers_count' => 2,
        ]);

        (new ExpirePendingBookingsJob)->handle();

        $tourDate->refresh();
        $this->assertEquals(TourDateStatus::Open, $tourDate->status);
        $this->assertEquals(8, $tourDate->booked_count);
    }

    public function test_does_not_expire_confirmed_bookings(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->makeCurrent();

        $user = User::factory()->customerOf($tenant)->create();
        $tour = Tour::factory()->for($tenant)->create();
        $tourDate = TourDate::factory()->for($tour)->create();

        $booking = Booking::factory()->for($tour)->for($tourDate)->for($user)->create([
            'status' => BookingStatus::Confirmed,
            'expires_at' => now()->subMinutes(5),
        ]);

        (new ExpirePendingBookingsJob)->handle();

        $booking->refresh();
        $this->assertEquals(BookingStatus::Confirmed, $booking->status);
    }
}
