<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Models\Hotel;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<Hotel>
 */
final class HotelBuilder extends Builder
{
    public function matching(?string $search): self
    {
        if ($search === null || $search === '') {
            return $this;
        }

        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

        return $this->where(fn (self $query) => $query
            ->where('name', 'like', $term)
            ->orWhere('city', 'like', $term)
            ->orWhere('address', 'like', $term)
            ->orWhere('contact_name', 'like', $term)
            ->orWhere('contact_phone', 'like', $term)
            ->orWhereHas('rooms', fn (Builder $rooms) => $rooms->where('name', 'like', $term)));
    }
}
