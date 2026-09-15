<?php

declare(strict_types=1);

namespace App\Http\Resources\Payment;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una transacción de la reserva vista desde la planilla: lo justo para saber
 * qué entró y abrir el detalle.
 *
 * @mixin Payment
 */
final class PaymentSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            // WHY: la etiqueta sale del enum y no de un mapa en el front. Con dos
            // catálogos el drawer decía «Procesando» y el listado «En proceso».
            'gateway' => $this->gateway->value,
            'gateway_label' => $this->gateway->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'amount' => $this->amount,
            'processed_at' => $this->processed_at?->toIso8601String(),
        ];
    }
}
