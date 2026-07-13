<?php

declare(strict_types=1);

namespace App\Http\Resources\Catalog;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Review
 */
final class HomeTestimonialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'title' => $this->title,
            'body' => $this->comment,
            'author_name' => $this->whenLoaded('user', fn () => $this->user?->name),
            'tour' => $this->whenLoaded('tour', fn () => [
                'name' => $this->tour?->name,
                'slug' => $this->tour?->slug,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
