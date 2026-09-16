<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Route;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ruta tal como la ve el producto que la opera: los datos de cabecera más la
 * marca de predeterminada que vive en el pivote.
 *
 * @mixin Route
 */
final class TourRouteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_default' => (bool) $this->pivot->is_default,
            'kind' => $this->kind?->value,
            'difficulty' => $this->difficulty?->value,
            'distance_km' => $this->distance_km,
            'duration_hours' => $this->duration_hours,
            'stops_count' => (int) ($this->stops_count ?? $this->stops->count()),
        ];
    }
}
