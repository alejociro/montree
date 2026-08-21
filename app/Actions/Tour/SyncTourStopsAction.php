<?php

declare(strict_types=1);

namespace App\Actions\Tour;

use App\Enums\TourStopKind;
use App\Models\Tour;

final class SyncTourStopsAction
{
    public function __construct(private NotifyPickupChangeAction $notifyPickupChange) {}

    /**
     * @param  array<int, array<string, mixed>>  $stops
     */
    public function handle(Tour $tour, array $stops): void
    {
        // Regla 6: la foto se toma antes de borrar, porque el sync reescribe
        // las paradas enteras y después ya no hay con qué comparar.
        $pickupBefore = $this->notifyPickupChange->snapshot($tour);

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

        $this->notifyPickupChange->handle($tour, $pickupBefore);
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
