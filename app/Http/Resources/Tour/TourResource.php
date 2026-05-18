<?php

declare(strict_types=1);

namespace App\Http\Resources\Tour;

use App\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tour
 */
class TourResource extends JsonResource
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
            'description' => $this->description,
            'status' => $this->status->value,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category !== null
                ? (new CategoryResource($this->category))->resolve()
                : null),
            'base_price' => $this->base_price,
            'currency' => $this->currency,
            'duration_hours' => $this->duration_hours,
            'difficulty' => $this->difficulty->value,
            'default_capacity' => $this->default_capacity,
            'meeting_point' => $this->meeting_point,
            'meeting_latitude' => $this->meeting_latitude,
            'meeting_longitude' => $this->meeting_longitude,
            'includes' => $this->includes ?? [],
            'excludes' => $this->excludes ?? [],
            'requirements' => $this->requirements ?? [],
            'rating_average' => $this->rating_average,
            'rating_count' => $this->rating_count,
            'images' => $this->whenLoaded(
                'images',
                fn () => TourImageResource::collection($this->images)->resolve(),
                fn () => [],
            ),
            'itinerary' => $this->whenLoaded(
                'itineraries',
                fn () => TourItineraryStepResource::collection($this->itineraries)->resolve(),
                fn () => [],
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
