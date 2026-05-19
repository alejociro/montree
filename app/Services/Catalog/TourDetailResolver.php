<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Tour;

final class TourDetailResolver
{
    public function bySlug(string $slug): ?Tour
    {
        return Tour::query()
            ->active()
            ->with([
                'category',
                'images',
                'itineraries',
                'dates' => fn ($q) => $q->openFuture()->orderBy('starts_at')->limit(12),
            ])
            ->where('slug', $slug)
            ->first();
    }
}
