<?php

declare(strict_types=1);

namespace App\Actions\Logistics;

use App\Exceptions\LogisticsRecordInUseException;
use App\Models\Provider;

final class DeleteProviderAction
{
    public function execute(Provider $provider): void
    {
        $departures = $provider->tourDates()->count();

        if ($departures > 0) {
            throw LogisticsRecordInUseException::providerUsedByDepartures($departures);
        }

        $provider->delete();
    }
}
