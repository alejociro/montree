<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\TourDate;
use App\Models\User;

final class AssignGuideAction
{
    public function handle(TourDate $tourDate, ?User $guide): TourDate
    {
        $tourDate->update(['guide_id' => $guide?->id]);

        return $tourDate->fresh();
    }
}
