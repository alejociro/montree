<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BookingStatus;
use App\Enums\TourDateStatus;
use App\Models\Booking;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

final class ExpirePendingBookingsJob implements ShouldQueue
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
        $bookings = Booking::query()
            ->where('status', BookingStatus::PendingPayment)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->with('tourDate')
            ->get();

        foreach ($bookings as $booking) {
            DB::transaction(function () use ($booking): void {
                $booking->update(['status' => BookingStatus::Expired]);

                $tourDate = $booking->tourDate;
                $tourDate->decrement('booked_count', $booking->travelers_count);

                if ($tourDate->status === TourDateStatus::Full) {
                    $tourDate->update(['status' => TourDateStatus::Open]);
                }
            });
        }
    }
}
