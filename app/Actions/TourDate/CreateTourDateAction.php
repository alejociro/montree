<?php

declare(strict_types=1);

namespace App\Actions\TourDate;

use App\Enums\TourDateStatus;
use App\Models\Tour;
use App\Models\TourDate;

final class CreateTourDateAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Tour $tour, array $data): TourDate
    {
        $tourDate = $tour->dates()->create([
            'guide_id' => $data['guide_id'] ?? null,
            'route_id' => $data['route_id'] ?? null,
            'provider_id' => $data['provider_id'] ?? null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'capacity' => $data['capacity'],
            'price_override' => $data['price_override'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => TourDateStatus::Open,
            'booked_count' => 0,
        ]);

        if (! empty($data['hotel_ids'])) {
            $tourDate->hotels()->sync($data['hotel_ids']);
        }

        return $tourDate;
    }
}
