<?php

declare(strict_types=1);

namespace App\Http\Resources\Catalog;

use App\Models\TourDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TourDate
 */
final class UpcomingDepartureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'available_seats' => max(0, $this->capacity - $this->booked_count),
            'effective_price' => $this->price_override ?? $this->tour->base_price,
            'tour' => [
                'name' => $this->tour->name,
                'slug' => $this->tour->slug,
                'currency' => $this->tour->currency,
                'cover_image_url' => $this->tour->coverImage?->url,
            ],
        ];
    }
}
