<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\RouteStop;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RouteStop
 */
final class RouteStopResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'name' => $this->name,
            'kind' => $this->kind->value,
            'time_label' => $this->time_label,
        ];
    }
}
