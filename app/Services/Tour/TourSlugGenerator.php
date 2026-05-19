<?php

declare(strict_types=1);

namespace App\Services\Tour;

use App\Models\Tour;
use Illuminate\Support\Str;

final class TourSlugGenerator
{
    public function generate(string $name, ?int $excludeTourId = null): string
    {
        $base = Str::slug($name) ?: 'tour';

        $candidate = $base;
        $suffix = 1;

        while ($this->collides($candidate, $excludeTourId)) {
            $suffix++;
            $candidate = $base.'-'.$suffix;
        }

        return $candidate;
    }

    private function collides(string $slug, ?int $excludeTourId): bool
    {
        return Tour::query()
            ->withTrashed()
            ->where('slug', $slug)
            ->when($excludeTourId !== null, fn ($query) => $query->where('id', '!=', $excludeTourId))
            ->exists();
    }
}
