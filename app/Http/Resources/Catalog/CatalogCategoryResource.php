<?php

declare(strict_types=1);

namespace App\Http\Resources\Catalog;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CatalogCategoryResource extends JsonResource
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
            'icon' => $this->icon,
            'tours_count' => (int) ($this->resource->getAttribute('tours_count') ?? 0),
        ];
    }
}
