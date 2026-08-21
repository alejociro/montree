<?php

declare(strict_types=1);

namespace App\Actions\Tour;

use App\Enums\TourStatus;
use App\Exceptions\InvalidTourStatusTransitionException;
use App\Models\Tour;
use App\Services\Tour\TourStatusTransition;

final class ChangeTourStatusAction
{
    public function __construct(private TourStatusTransition $transition) {}

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

    private function assertActivationReady(Tour $tour): void
    {
        if ($tour->images()->count() === 0) {
            throw InvalidTourStatusTransitionException::needsImage();
        }

        if ($tour->default_guide_id === null) {
            throw InvalidTourStatusTransitionException::needsDefaultGuide();
        }
    }
}
