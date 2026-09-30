<?php

declare(strict_types=1);

namespace App\Actions\Tour;

use App\Enums\Currency;
use App\Enums\TourStatus;
use App\Models\Tenant;
use App\Models\Tour;
use App\Services\Tour\TourSlugGenerator;
use Illuminate\Support\Facades\DB;

final class CreateTourAction
{
    public function __construct(
        private TourSlugGenerator $slugGenerator,
        private SyncTourItineraryAction $syncItinerary,
        private SyncTourStopsAction $syncStops,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Tenant $tenant, array $data): Tour
    {
        return DB::transaction(function () use ($tenant, $data): Tour {
            $tour = new Tour;
            $tour->fill($this->withoutRelations($data));
            $tour->currency = $tenant->configuration?->currency ?? Currency::FALLBACK;
            $tour->slug = $this->slugGenerator->generate($data['name']);
            $tour->status = TourStatus::Draft;
            $tour->save();

            if (isset($data['itinerary']) && is_array($data['itinerary'])) {
                $this->syncItinerary->handle($tour, $data['itinerary']);
            }

            if (isset($data['stops']) && is_array($data['stops'])) {
                $this->syncStops->handle($tour, $data['stops']);
            }

            return $tour->fresh(['category', 'images', 'itineraries', 'stops']) ?? $tour;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withoutRelations(array $data): array
    {
        unset($data['itinerary'], $data['stops']);

        return $data;
    }
}
