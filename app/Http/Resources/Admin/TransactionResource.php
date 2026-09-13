<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fila del listado de transacciones.
 *
 * WHY: ni acá ni en el detalle viajan `process_url` (enlace de cobro vivo) ni
 * `gateway_response` (volcado crudo con datos del pagador).
 *
 * @mixin Payment
 */
final class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'request_id' => $this->request_id,
            'gateway' => $this->gateway->value,
            'gateway_label' => $this->gateway->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'gateway_status' => $this->gateway_status,
            'status_message' => $this->status_message,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'type' => $this->type->value,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'booking' => $this->whenLoaded('booking', fn (): array => (new TransactionBookingResource($this->booking))->resolve($request)),
        ];
    }
}
