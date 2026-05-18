<?php

declare(strict_types=1);

namespace App\Http\Resources\Promotion;

use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Promotion
 */
class PromotionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $now = now();

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type->value,
            'value' => $this->value,
            'max_discount' => $this->max_discount,
            'min_amount' => $this->min_amount,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'max_uses' => $this->max_uses,
            'uses_count' => $this->uses_count,
            'max_uses_per_user' => $this->max_uses_per_user,
            'is_active' => $this->is_active,
            'is_expired' => $this->ends_at !== null && $this->ends_at->lessThan($now),
            'is_exhausted' => $this->max_uses !== null && $this->uses_count >= $this->max_uses,
            'applicable_tours' => $this->applicable_tours ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
