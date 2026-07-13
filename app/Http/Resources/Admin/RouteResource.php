<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Route;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Route
 */
final class RouteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'distance_km' => $this->distance_km,
            'duration_hours' => $this->duration_hours,
            'tour_dates_count' => (int) ($this->tour_dates_count ?? 0),
        ];
    }
}
