<?php

declare(strict_types=1);

namespace App\Http\Resources\Tour;

use App\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Tour
 */
class TourSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'short_description' => $this->short_description,
            'status' => $this->status->value,
            'base_price' => $this->base_price,
            'currency' => $this->currency,
            'duration_hours' => $this->duration_hours,
            'difficulty' => $this->difficulty->value,
            'default_capacity' => $this->default_capacity,
            'category' => $this->whenLoaded('category', fn () => $this->category !== null
                ? (new CategoryResource($this->category))->resolve()
                : null),
            'cover_image_url' => $this->whenLoaded('coverImage', fn () => $this->coverImage !== null
                ? Storage::disk('public')->url($this->coverImage->path)
                : null),
            'images_count' => $this->whenCounted('images'),
            'bookings_count' => $this->whenCounted('bookings'),
            'rating_average' => $this->rating_average,
            'rating_count' => $this->rating_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
