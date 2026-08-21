<?php

declare(strict_types=1);

namespace App\Actions\Tour;

use App\Enums\TourStatus;
use App\Exceptions\InvalidTourStatusTransitionException;
use App\Models\Tour;
use App\Services\Tour\TourPublishChecklist;
use App\Services\Tour\TourStatusTransition;

final class ChangeTourStatusAction
{
    public function __construct(
        private TourStatusTransition $transition,
        private TourPublishChecklist $checklist,
    ) {}

    public function handle(Tour $tour, TourStatus $next): Tour
    {
        if (! $this->transition->isValid($tour->status, $next)) {
            throw new InvalidTourStatusTransitionException($tour->status, $next);
        }

        if ($next === TourStatus::Active) {
            $this->assertActivationReady($tour);
        }

        $tour->status = $next;
        $tour->save();

        return $tour->fresh(['category', 'images', 'itineraries']) ?? $tour;
    }

    /**
     * WHY: las condiciones no se listan dos veces. `TourPublishChecklist` es la
     * misma lista que viaja en `TourResource` y pinta el riel «Para publicar»,
     * así que la pantalla no puede prometer una condición que esto no exija.
     */
    private function assertActivationReady(Tour $tour): void
    {
        $pending = $this->checklist->pendingBlocking($tour);

        if ($pending === []) {
            return;
        }

        throw match ($pending[0]) {
            TourPublishChecklist::REQUIREMENT_IMAGE => InvalidTourStatusTransitionException::needsImage(),
            TourPublishChecklist::REQUIREMENT_GUIDE => InvalidTourStatusTransitionException::needsDefaultGuide(),
            TourPublishChecklist::REQUIREMENT_SUMMARY => InvalidTourStatusTransitionException::needsSummary(),
            default => InvalidTourStatusTransitionException::incomplete(),
        };
    }
}
