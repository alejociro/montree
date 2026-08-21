<?php

declare(strict_types=1);

namespace App\Actions\TourDate;

use App\Enums\TourDateStatus;
use App\Models\Tour;
use App\Models\TourDate;
use Illuminate\Support\Carbon;

final class CreateTourDateAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Tour $tour, array $data): TourDate
    {
        $startsAt = Carbon::parse($data['starts_at']);

        $tourDate = $tour->dates()->create([
            'guide_id' => $data['guide_id'],
            'route_id' => $data['route_id'] ?? null,
            'provider_id' => $data['provider_id'] ?? null,
            'starts_at' => $startsAt,
            // WHY (D9): el fin sale de la duración del tour, no del cliente.
            'ends_at' => TourDate::deriveEndsAt($startsAt, $tour->duration_hours),
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
