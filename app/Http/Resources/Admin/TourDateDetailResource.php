<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\TourDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TourDate
 */
final class TourDateDetailResource extends JsonResource
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
            'capacity' => $this->capacity,
            'booked_count' => $this->booked_count,
            'available_seats' => max(0, $this->capacity - $this->booked_count),
            'price_override' => $this->price_override,
            'effective_price' => $this->price_override ?? $this->tour->base_price,
            'status' => $this->status->value,
            'notes' => $this->notes,
            'guide' => $this->whenLoaded('guide', fn () => $this->guide
                ? ['id' => $this->guide->id, 'name' => $this->guide->name]
                : null),
            'route' => $this->whenLoaded('route', fn () => $this->route
                ? ['id' => $this->route->id, 'name' => $this->route->name]
                : null),
            'provider' => $this->whenLoaded('provider', fn () => $this->provider
                ? ['id' => $this->provider->id, 'name' => $this->provider->name]
                : null),
            'hotels' => $this->whenLoaded('hotels', fn () => $this->hotels
                ->map(fn ($hotel) => ['id' => $hotel->id, 'name' => $hotel->name])
                ->values()),
        ];
    }
}
