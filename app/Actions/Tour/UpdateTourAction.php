<?php

declare(strict_types=1);

namespace App\Actions\Tour;

use App\Models\Tour;
use App\Services\Tour\TourSlugGenerator;
use Illuminate\Support\Facades\DB;

final class UpdateTourAction
{
    public function __construct(
        private TourSlugGenerator $slugGenerator,
        private SyncTourItineraryAction $syncItinerary,
        private SyncTourStopsAction $syncStops,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Tour $tour, array $data): Tour
    {
        return DB::transaction(function () use ($tour, $data): Tour {
            $payload = $this->withoutRelations($data);

            if (isset($payload['name']) && $payload['name'] !== $tour->name) {
                $payload['slug'] = $this->slugGenerator->generate($payload['name'], $tour->id);
            }

            $tour->fill($payload);
            $tour->save();

            if (array_key_exists('itinerary', $data) && is_array($data['itinerary'])) {
                $this->syncItinerary->handle($tour, $data['itinerary']);
            }

            if (array_key_exists('stops', $data) && is_array($data['stops'])) {
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
