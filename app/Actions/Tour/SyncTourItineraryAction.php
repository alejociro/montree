<?php

declare(strict_types=1);

namespace App\Actions\Tour;

use App\Models\Tour;

final class SyncTourItineraryAction
{
    /**
     * @param  array<int, array<string, mixed>>  $steps
     */
    public function handle(Tour $tour, array $steps): void
    {
        $tour->itineraries()->delete();

        foreach ($steps as $step) {
            $tour->itineraries()->create([
                'step_number' => (int) $step['step_number'],
                'title' => (string) $step['title'],
                'description' => (string) ($step['description'] ?? ''),
                'duration_label' => isset($step['duration_label']) && $step['duration_label'] !== ''
                    ? (string) $step['duration_label']
                    : null,
            ]);
        }
    }
}
