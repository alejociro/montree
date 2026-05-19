<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Enums\ReviewStatus;
use App\Models\Tour;
use Illuminate\Support\Facades\DB;

final class RatingDistribution
{
    /**
     * @return array<int, int> keys 1..5
     */
    public static function forTour(Tour $tour): array
    {
        $counts = DB::table('reviews')
            ->where('tour_id', $tour->id)
            ->where('status', ReviewStatus::Approved->value)
            ->whereNull('deleted_at')
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating')
            ->all();

        return [
            5 => (int) ($counts[5] ?? 0),
            4 => (int) ($counts[4] ?? 0),
            3 => (int) ($counts[3] ?? 0),
            2 => (int) ($counts[2] ?? 0),
            1 => (int) ($counts[1] ?? 0),
        ];
    }
}
