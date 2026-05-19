<?php

declare(strict_types=1);

namespace App\Http\Resources\Catalog;

use App\Models\Tour;
use App\Services\Catalog\RatingDistribution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Tour
 */
final class PublicTourResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $cover = $this->images->firstWhere('is_cover', true) ?? $this->images->first();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'base_price' => $this->base_price,
            'currency' => $this->currency,
            'duration_hours' => $this->duration_hours,
            'difficulty' => $this->difficulty->value,
            'default_capacity' => $this->default_capacity,
            'category' => $this->whenLoaded('category', fn () => $this->category === null ? null : [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'rating_average' => $this->rating_average,
            'rating_count' => $this->rating_count,
            'rating_distribution' => RatingDistribution::forTour($this->resource),
            'images' => $this->images->map(fn ($img) => [
                'id' => $img->id,
                'url' => self::publicUrl($img->path),
                'is_cover' => (bool) $img->is_cover,
                'alt_text' => $img->alt_text,
                'display_order' => $img->display_order,
            ])->values(),
            'cover_image_url' => self::publicUrl($cover?->path),
            'itinerary' => $this->itineraries->map(fn ($step) => [
                'step_number' => $step->step_number,
                'title' => $step->title,
                'description' => $step->description,
                'duration_label' => $step->duration_label,
            ])->values(),
            'requirements' => $this->requirements ?? [],
            'includes' => $this->includes ?? [],
            'meeting_point' => $this->meeting_point,
            'meeting_latitude' => $this->meeting_latitude,
            'meeting_longitude' => $this->meeting_longitude,
            'future_dates' => $this->dates->map(fn ($d) => [
                'id' => $d->id,
                'starts_at' => $d->starts_at->toIso8601String(),
                'ends_at' => $d->ends_at?->toIso8601String(),
                'price_override' => $d->price_override,
                'effective_price' => $d->price_override ?? $this->base_price,
                'capacity_total' => $d->capacity,
                'capacity_booked' => $d->booked_count,
                'available_seats' => max(0, $d->capacity - $d->booked_count),
                'is_full' => $d->booked_count >= $d->capacity,
                'status' => $d->status->value,
            ])->values(),
            'is_favorite' => (bool) ($this->is_favorite ?? false),
        ];
    }

    private static function publicUrl(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : Storage::disk('public')->url($path);
    }
}
