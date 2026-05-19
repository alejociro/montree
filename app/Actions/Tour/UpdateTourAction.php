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
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Tour $tour, array $data): Tour
    {
        return DB::transaction(function () use ($tour, $data): Tour {
            $payload = $this->withoutItinerary($data);

            if (isset($payload['name']) && $payload['name'] !== $tour->name) {
                $payload['slug'] = $this->slugGenerator->generate($payload['name'], $tour->id);
            }

            $tour->fill($payload);
            $tour->save();

            if (array_key_exists('itinerary', $data) && is_array($data['itinerary'])) {
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
