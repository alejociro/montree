<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Route;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Route
 */
final class RouteOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'kind' => $this->kind?->value,
            'difficulty' => $this->difficulty?->value,
        ];
    }
}
