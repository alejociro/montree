<?php

declare(strict_types=1);

namespace App\Http\Resources\Promotion;

use App\Data\PromotionValidationResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property PromotionValidationResult $resource
 */
class PromotionValidationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'promotion_id' => $this->resource->promotion->id,
            'code' => $this->resource->promotion->code,
            'discount' => $this->resource->discount,
            'total_after' => $this->resource->totalAfter,
        ];
    }
}
