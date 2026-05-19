<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Notifications\BookingReminderNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class SendBookingReminderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 60, 120];

    public function handle(): void
    {
        $windowStart = now()->addHours(23);
        $windowEnd = now()->addHours(25);

        $bookings = Booking::query()
            ->where('status', BookingStatus::Confirmed)
            ->whereHas('tourDate', fn ($q) => $q->whereBetween('starts_at', [$windowStart, $windowEnd]))
            ->with(['tour', 'tourDate', 'user'])
            ->get();

        foreach ($bookings as $booking) {
            $alreadyNotified = $booking->user
                ->notifications()
                ->where('type', BookingReminderNotification::class)
                ->whereJsonContains('data->booking_id', $booking->id)
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            $booking->user->notify(
                BookingReminderNotification::fromBooking($booking),
            );
        }
    }
}
