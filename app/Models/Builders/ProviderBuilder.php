<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Models\Provider;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<Provider>
 */
final class ProviderBuilder extends Builder
{
    public function matching(?string $search): self
    {
        if ($search === null || $search === '') {
            return $this;
        }

        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

        return $this->where(fn (self $query) => $query
            ->where('name', 'like', $term)
            ->orWhere('legal_name', 'like', $term)
            ->orWhere('tax_id', 'like', $term)
            ->orWhere('city', 'like', $term)
            ->orWhere('contact_name', 'like', $term)
            ->orWhere('contact_phone', 'like', $term)
            ->orWhereHas('rates', fn (Builder $rates) => $rates->where('concept', 'like', $term)));
    }
}
