<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\ProviderDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProviderDocument
 */
final class ProviderDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'kind' => $this->kind->value,
            'number' => $this->number,
            'expires_at' => $this->expires_at?->toDateString(),
        ];
    }
}
