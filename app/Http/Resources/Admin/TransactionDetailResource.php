<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detalle de una transacción: la foto que el comercio le pasa al soporte de la
 * pasarela.
 *
 * WHY: `process_url` y `gateway_response` no salen —uno es un enlace de cobro
 * vivo y el otro un volcado con datos del pagador—, y de `processor_fields`
 * solo viajan los últimos cuatro dígitos: el BIN se queda en la base.
 *
 * @mixin Payment
 */
final class TransactionDetailResource extends JsonResource
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
            'internal_reference' => $this->internal_reference,
            'gateway' => $this->gateway->value,
            'gateway_label' => $this->gateway->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'gateway_status' => $this->gateway_status,
            'status_message' => $this->status_message,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'authorization' => $this->authorization,
            'receipt' => $this->receipt,
            'franchise' => $this->franchise,
            'payment_method' => $this->payment_method,
            'payment_method_name' => $this->payment_method_name,
            'issuer_name' => $this->issuer_name,
            'last_digits' => $this->last_digits,
            'processor_fields' => $this->safeProcessorFields(),
            'session_expires_at' => $this->session_expires_at?->toIso8601String(),
            'processed_at' => $this->processed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'is_queryable' => $this->isQueryable(),
            'booking' => $this->whenLoaded('booking', fn (): array => (new TransactionBookingDetailResource($this->booking))->resolve($request)),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function safeProcessorFields(): array
    {
        $lastDigits = $this->last_digits;

        return $lastDigits === null ? [] : ['lastDigits' => $lastDigits];
    }
}
