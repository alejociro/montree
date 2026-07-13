<?php

declare(strict_types=1);

namespace App\Actions\TourDate;

use App\Exceptions\TourDateException;
use App\Models\TourDate;

final class DeleteTourDateAction
{
    public function handle(TourDate $tourDate): void
    {
        if ($tourDate->bookings()->exists()) {
            throw TourDateException::hasBookings();
        }

        $tourDate->hotels()->detach();
        $tourDate->delete();
    }
}
