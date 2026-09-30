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
                'stops',
                // La salida elegida manda sobre el producto: su ruta y su guía
                // alimentan el mapa y la ficha logística del detalle (spec §G).
                'dates' => fn ($q) => $q->bookable()->orderBy('starts_at')->limit(12),
                'dates.route.stops',
                'dates.guide',
            ])
            ->where('slug', $slug)
            ->first();
    }
}
