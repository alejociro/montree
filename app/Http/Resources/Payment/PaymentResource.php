<?php

declare(strict_types=1);

namespace App\Http\Resources\Payment;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
final class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'gateway' => $this->gateway->value,
            'gateway_payment_id' => $this->gateway_payment_id,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'refunded_amount' => $this->refunded_amount,
        ];
    }
}
