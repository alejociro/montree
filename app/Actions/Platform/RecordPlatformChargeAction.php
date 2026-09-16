<?php

declare(strict_types=1);

namespace App\Actions\Platform;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PlatformCharge;
use App\Models\Tenant;
use App\Services\Platform\PlatformChargeCalculator;

/**
 * Asienta el cobro de la plataforma por una reserva confirmada.
 *
 * Idempotente por `booking_id` (único en la tabla): una reserva pagada en dos
 * abonos confirma una sola vez, pero el reintento de una notificación de la
 * pasarela sí puede volver a pasar por acá.
 */
final class RecordPlatformChargeAction
{
    public function __construct(private PlatformChargeCalculator $calculator) {}

    public function execute(Booking $booking, ?Payment $payment = null): ?PlatformCharge
    {
        $tenant = Tenant::query()->find($booking->tenant_id);

        if ($tenant === null || $tenant->commission_type === null || $tenant->commission_value === null) {
            return null;
        }

        $appliedValue = (string) $tenant->commission_value;

        return PlatformCharge::query()->firstOrCreate(
            ['booking_id' => $booking->id],
            [
                'tenant_id' => $tenant->id,
                'payment_id' => $payment?->id,
                'base_amount' => $booking->total_amount,
                'commission_type' => $tenant->commission_type,
                'applied_value' => $appliedValue,
                'amount' => $this->calculator->calculate(
                    $tenant->commission_type,
                    $appliedValue,
                    (string) $booking->total_amount,
                ),
                'currency' => $this->currency($tenant, $booking),
                'charged_at' => $booking->confirmed_at ?? now(),
            ],
        );
    }

    /**
     * WHY: un cobro fijo está expresado en la moneda con la que opera la agencia,
     * no en la de la reserva; el feature no convierte monedas (spec, edge cases).
     */
    private function currency(Tenant $tenant, Booking $booking): string
    {
        $tenant->loadMissing('configuration');

        return $tenant->configuration?->currency ?? $booking->currency;
    }
}
