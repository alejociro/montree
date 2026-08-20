<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Enums\ReviewStatus;
use App\Models\Tour;
use Illuminate\Support\Facades\DB;

final class RatingDistribution
{
    /**
     * WHY: devuelve un objeto, no un array. `JsonResource` re-indexa con
     * `array_values` cualquier array cuyas claves sean todas numéricas, así que
     * el mapa estrella→conteo salía al front como la lista `[2,1,0,0,0]` y se
     * perdía a qué estrella pertenecía cada conteo.
     *
     * @return object{5: int, 4: int, 3: int, 2: int, 1: int}
     */
    public static function forTour(Tour $tour): object
    {
        $counts = DB::table('reviews')
            ->where('tour_id', $tour->id)
            ->where('status', ReviewStatus::Approved->value)
            ->whereNull('deleted_at')
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating')
            ->all();

        return (object) [
            '5' => (int) ($counts[5] ?? 0),
            '4' => (int) ($counts[4] ?? 0),
            '3' => (int) ($counts[3] ?? 0),
            '2' => (int) ($counts[2] ?? 0),
            '1' => (int) ($counts[1] ?? 0),
        ];
    }
}
