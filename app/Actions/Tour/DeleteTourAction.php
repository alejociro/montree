<?php

declare(strict_types=1);

namespace App\Actions\Tour;

use App\Enums\BookingStatus;
use App\Exceptions\TourHasActiveBookingsException;
use App\Models\Tour;

final class DeleteTourAction
{
    public function handle(Tour $tour): void
    {
        if ($this->hasBlockingBookings($tour)) {
            throw new TourHasActiveBookingsException;
        }

        $tour->delete();
    }

    private function hasBlockingBookings(Tour $tour): bool
    {
        return $tour->bookings()
            ->whereIn('status', [
                BookingStatus::PendingPayment->value,
                BookingStatus::Confirmed->value,
            ])
            ->exists();
    }
}
