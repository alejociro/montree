<?php

declare(strict_types=1);

namespace App\Http\Resources\Tour;

use App\Models\TourImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin TourImage
 */
class TourImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tour_id' => $this->tour_id,
            'url' => Storage::disk('public')->url($this->path),
            'alt_text' => $this->alt_text,
            'display_order' => $this->display_order,
            'is_cover' => (bool) $this->is_cover,
        ];
    }
}
