<?php

declare(strict_types=1);

namespace App\Actions\Tour;

use App\Enums\TourStatus;
use App\Exceptions\PlanLimitReachedException;
use App\Models\Tenant;
use App\Models\Tour;
use App\Services\Tour\PlanLimitChecker;
use App\Services\Tour\TourSlugGenerator;
use Illuminate\Support\Facades\DB;

final class CreateTourAction
{
    public function __construct(
        private TourSlugGenerator $slugGenerator,
        private PlanLimitChecker $planLimits,
        private SyncTourItineraryAction $syncItinerary,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Tenant $tenant, array $data): Tour
    {
        if (! $this->planLimits->canCreateTour($tenant)) {
            throw PlanLimitReachedException::tours($this->planLimits->maxToursForTenant($tenant));
        }

        return DB::transaction(function () use ($data): Tour {
            $tour = new Tour;
            $tour->fill($this->withoutItinerary($data));
            $tour->slug = $this->slugGenerator->generate($data['name']);
            $tour->status = TourStatus::Draft;
            $tour->save();

            if (isset($data['itinerary']) && is_array($data['itinerary'])) {
                $this->syncItinerary->handle($tour, $data['itinerary']);
            }

            return $tour->fresh(['category', 'images', 'itineraries']) ?? $tour;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withoutItinerary(array $data): array
    {
        unset($data['itinerary']);

        return $data;
    }
}
