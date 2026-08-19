<?php

declare(strict_types=1);

namespace App\Actions\TourDate;

use App\Enums\TourDateStatus;
use App\Exceptions\TourDateException;
use App\Models\TourDate;

final class CancelTourDateAction
{
    public function handle(TourDate $tourDate, ?string $reason): TourDate
    {
        if ($tourDate->status === TourDateStatus::Cancelled) {
            throw TourDateException::alreadyCancelled();
        }

        $tourDate->status = TourDateStatus::Cancelled;

        if ($reason !== null && $reason !== '') {
            $tourDate->notes = trim(($tourDate->notes ? $tourDate->notes."\n" : '').__('Cancelación: ').$reason);
        }

        $tourDate->save();

        return $tourDate->fresh() ?? $tourDate;
    }
}
