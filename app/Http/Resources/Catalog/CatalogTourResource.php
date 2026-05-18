<?php

declare(strict_types=1);

namespace App\Http\Resources\Catalog;

use App\Http\Resources\Tour\CategoryResource;
use App\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Tour
 *
 * @property string|null $next_date_starts_at injected by TourCatalogQuery
 */
class CatalogTourResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $nextDate = $this->resolveNextDate();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'short_description' => $this->short_description,
            'base_price' => $this->base_price,
            'currency' => $this->currency,
            'duration_hours' => $this->duration_hours,
            'difficulty' => $this->difficulty->value,
            'default_capacity' => $this->default_capacity,
            'category' => $this->category !== null
                ? (new CategoryResource($this->category))->resolve()
                : null,
            'cover_image_url' => $this->coverImage !== null
                ? Storage::disk('public')->url($this->coverImage->path)
                : null,
            'rating_average' => $this->rating_average,
            'rating_count' => $this->rating_count,
            'next_date_starts_at' => $nextDate?->toIso8601String(),
            'has_future_dates' => $nextDate !== null,
            'is_favorite' => (bool) ($this->resource->getAttribute('is_favorite') ?? false),
        ];
    }

    private function resolveNextDate(): ?Carbon
    {
        $raw = $this->resource->getAttribute('next_date_starts_at');

        if ($raw === null) {
            return null;
        }

        return $raw instanceof Carbon ? $raw : Carbon::parse((string) $raw);
    }
}
