<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Provider
 */
final class ProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'service_type' => $this->service_type?->value,
            'description' => $this->description,
            'legal_name' => $this->legal_name,
            'tax_id' => $this->tax_id,
            'tax_regime' => $this->tax_regime?->value,
            'billing_email' => $this->billing_email,
            'bank_account' => $this->bank_account,
            'payment_terms' => $this->payment_terms?->value,
            'contact_name' => $this->contact_name,
            'contact_role' => $this->contact_role,
            'contact_phone' => $this->contact_phone,
            'contact_email' => $this->contact_email,
            'alternate_contact' => $this->alternate_contact,
            'service_hours' => $this->service_hours,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'city' => $this->city,
            'state' => $this->state,
            'coverage' => $this->coverage,
            'currency' => $this->currency,
            'rates_valid_until' => $this->rates_valid_until?->toDateString(),
            'notes' => $this->notes,
            'rates' => ProviderRateResource::collection($this->whenLoaded('rates', fn () => $this->rates, collect()))->resolve(),
            'documents' => ProviderDocumentResource::collection($this->whenLoaded('documents', fn () => $this->documents, collect()))->resolve(),
            'tour_dates_count' => (int) ($this->tour_dates_count ?? 0),
        ];
    }
}
