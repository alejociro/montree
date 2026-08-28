<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\ProviderRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProviderRate
 */
final class ProviderRateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'concept' => $this->concept,
            'amount' => $this->amount,
            'unit' => $this->unit->value,
        ];
    }
}
