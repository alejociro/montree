<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use App\Rules\GuideIsAvailable;
use Illuminate\Support\Facades\Validator;

final class AssignGuideAction
{
    /**
     * WHY (D9): la Action revalida la disponibilidad aunque el Form Request ya
     * la haya corrido. Es el punto por el que pasa toda asignación desde el
     * panel; que la regla dependa de que cada llamador se acuerde de aplicarla
     * es justo el agujero que este cambio cierra.
     */
    public function handle(TourDate $tourDate, User $guide): TourDate
    {
        $this->assertAvailable($tourDate, $guide);

        $tourDate->update(['guide_id' => $guide->id]);

        return $tourDate->fresh();
    }

    private function assertAvailable(TourDate $tourDate, User $guide): void
    {
        $tour = $tourDate->tour;

        if (! $tour instanceof Tour) {
            return;
        }

        $rule = new GuideIsAvailable(
            $tourDate->starts_at,
            TourDate::deriveEndsAt($tourDate->starts_at, $tour->duration_hours),
            (int) $tourDate->getKey(),
        );

        Validator::make(['guide_id' => $guide->id], ['guide_id' => [$rule]])->validate();
    }
}
