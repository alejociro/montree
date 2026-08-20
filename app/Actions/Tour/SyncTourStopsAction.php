<?php

declare(strict_types=1);

namespace App\Actions\Tour;

use App\Enums\TourStopKind;
use App\Models\Tour;

final class SyncTourStopsAction
{
    /**
     * @param  array<int, array<string, mixed>>  $stops
     */
    public function handle(Tour $tour, array $stops): void
    {
        $tour->stops()->delete();
        $siteNumber = 0;

        foreach (array_values($stops) as $index => $stop) {
            $kind = TourStopKind::from((string) $stop['kind']);

            if ($kind === TourStopKind::Site) {
                $siteNumber++;
            }

            $tour->stops()->create([
                'position' => $index + 1,
                'kind' => $kind,
                'code' => $this->codeFor($kind, $siteNumber),
                'label' => $this->trimmedOrNull($stop['label'] ?? null),
                'name' => (string) $stop['name'],
                'place' => $this->trimmedOrNull($stop['place'] ?? null),
                'time_label' => $this->trimmedOrNull($stop['time'] ?? null),
                'latitude' => $stop['latitude'],
                'longitude' => $stop['longitude'],
                'itinerary_step' => isset($stop['itinerary_step']) && $stop['itinerary_step'] !== ''
                    ? (int) $stop['itinerary_step']
                    : null,
            ]);
        }
    }

    private function codeFor(TourStopKind $kind, int $siteNumber): string
    {
        return match ($kind) {
            TourStopKind::Pickup => 'A',
            TourStopKind::Drop => 'B',
            TourStopKind::Site => (string) $siteNumber,
        };
    }

    private function trimmedOrNull(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}
