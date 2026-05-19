<?php

declare(strict_types=1);

namespace App\Http\Resources\Review;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Review
 */
final class ReviewResource extends JsonResource
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
            'comment' => $this->comment,
            'status' => $this->status->value,
            'rejection_reason' => $this->rejection_reason,
            'admin_response' => $this->admin_response,
            'admin_responded_at' => $this->responded_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'tour' => $this->whenLoaded('tour', fn () => [
                'id' => $this->tour->id,
                'name' => $this->tour->name,
                'slug' => $this->tour->slug,
            ]),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
        ];
    }
}
