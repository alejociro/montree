<?php

declare(strict_types=1);

namespace App\Actions\Logistics;

use App\Exceptions\LogisticsRecordInUseException;
use App\Models\Hotel;

final class DeleteHotelAction
{
    public function execute(Hotel $hotel): void
    {
        $departures = $hotel->tourDates()->count();

        if ($departures > 0) {
            throw LogisticsRecordInUseException::hotelUsedByDepartures($departures);
        }

        $hotel->delete();
    }
}
