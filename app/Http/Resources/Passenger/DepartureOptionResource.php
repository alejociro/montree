<?php

declare(strict_types=1);

namespace App\Http\Resources\Passenger;

use App\Models\TourDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Salida como opción del selector de la planilla. `guide` nunca es `null`:
 * `tour_dates.guide_id` es `NOT NULL` desde la Fase 1 (D2).
 *
 * @mixin TourDate
 */
final class DepartureOptionResource extends JsonResource
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
            'guide' => $this->whenLoaded('guide', fn (): ?array => $this->guide === null ? null : [
                'id' => $this->guide->id,
                'name' => $this->guide->name,
            ]),
            'status' => $this->status->value,
        ];
    }
}
