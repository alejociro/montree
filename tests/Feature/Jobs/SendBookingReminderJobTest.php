<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Enums\BookingStatus;
use App\Jobs\SendBookingReminderJob;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use App\Notifications\BookingReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SendBookingReminderJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_reminder_for_bookings_starting_in_24_hours(): void
    {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        $tenant->makeCurrent();

        $user = User::factory()->customerOf($tenant)->create();
        $tour = Tour::factory()->for($tenant)->create();
        $tourDate = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addHours(24),
        ]);

        $booking = Booking::factory()->for($tour)->for($tourDate)->for($user)->create([
            'status' => BookingStatus::Confirmed,
        ]);

        (new SendBookingReminderJob)->handle();

        Notification::assertSentTo($user, BookingReminderNotification::class);
    }

    public function test_does_not_send_reminder_for_non_confirmed_bookings(): void
    {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        $tenant->makeCurrent();

        $user = User::factory()->customerOf($tenant)->create();
        $tour = Tour::factory()->for($tenant)->create();
        $tourDate = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addHours(24),
        ]);

        Booking::factory()->for($tour)->for($tourDate)->for($user)->create([
            'status' => BookingStatus::PendingPayment,
        ]);

        (new SendBookingReminderJob)->handle();

        Notification::assertNothingSent();
    }

    public function test_does_not_send_duplicate_reminder(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->makeCurrent();

        $user = User::factory()->customerOf($tenant)->create();
        $tour = Tour::factory()->for($tenant)->create();
        $tourDate = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addHours(24),
        ]);

        $booking = Booking::factory()->for($tour)->for($tourDate)->for($user)->create([
            'status' => BookingStatus::Confirmed,
        ]);

        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => BookingReminderNotification::class,
            'data' => ['booking_id' => $booking->id],
        ]);

        Notification::fake();

        (new SendBookingReminderJob)->handle();

        Notification::assertNothingSent();
    }

    public function test_does_not_send_for_tours_outside_24h_window(): void
    {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        $tenant->makeCurrent();

        $user = User::factory()->customerOf($tenant)->create();
        $tour = Tour::factory()->for($tenant)->create();
        $tourDate = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addHours(48),
        ]);

        Booking::factory()->for($tour)->for($tourDate)->for($user)->create([
            'status' => BookingStatus::Confirmed,
        ]);

        (new SendBookingReminderJob)->handle();

        Notification::assertNothingSent();
    }
}
