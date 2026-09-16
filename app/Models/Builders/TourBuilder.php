<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Data\Tour\TourFilters;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<Tour>
 */
final class TourBuilder extends Builder
{
    public function applyFilters(TourFilters $filters): self
    {
        return $this
            ->when($filters->status !== null, fn (self $query) => $query->where('status', $filters->status))
            ->when($filters->categoryId !== null, fn (self $query) => $query->where('category_id', $filters->categoryId))
            ->when($filters->search !== null, fn (self $query) => $query->matching((string) $filters->search));
    }

    public function matching(string $search): self
    {
        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

        return $this->where(fn (self $query) => $query
            ->where('name', 'like', $term)
            ->orWhere('description', 'like', $term));
    }
}
