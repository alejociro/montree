<?php

declare(strict_types=1);

namespace App\Actions\Platform;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PlatformCharge;
use App\Models\Tenant;
use App\Services\Platform\CommissionScheduleResolver;
use App\Services\Platform\PlatformChargeCalculator;
use Illuminate\Support\Facades\Log;

/**
 * Asienta el cobro de la plataforma por una reserva confirmada.
 *
 * Idempotente por `booking_id` (único en la tabla): una reserva pagada en dos
 * abonos confirma una sola vez, pero el reintento de una notificación de la
 * pasarela sí puede volver a pasar por acá.
 */
final class RecordPlatformChargeAction
{
    public function __construct(
        private PlatformChargeCalculator $calculator,
        private CommissionScheduleResolver $resolver,
    ) {}

    public function execute(Booking $booking, ?Payment $payment = null): ?PlatformCharge
    {
        $tenant = Tenant::query()->find($booking->tenant_id);

        if ($tenant === null) {
            return null;
        }

        $tenant->loadMissing('configuration');
        $tenantCurrency = $tenant->configuration?->currency ?? $booking->currency;

        ['schedule' => $schedule, 'scope' => $scope] = $this->resolver->resolve($tenant);

        // WHY: el esquema está expresado en una sola moneda. Si no coincide con
        // la de la agencia y ella no tiene esquema propio, no hay tarifa que
        // aplicar: no se inventa una conversión (spec, edge cases).
        if ($schedule->currency !== $tenantCurrency) {
            Log::warning('Platform charge skipped: schedule currency does not match tenant currency.', [
                'tenant_id' => $tenant->id,
                'booking_id' => $booking->id,
                'schedule_scope' => $scope,
                'schedule_currency' => $schedule->currency,
                'tenant_currency' => $tenantCurrency,
            ]);

            return null;
        }

        $result = $this->calculator->calculateForTiers(
            $schedule->tiers,
            (string) $booking->total_amount,
            $schedule->max_charge === null ? null : (string) $schedule->max_charge,
        );

        if ($result === null) {
            Log::warning('Platform charge skipped: no tier covers the booking amount.', [
                'tenant_id' => $tenant->id,
                'booking_id' => $booking->id,
                'schedule_scope' => $scope,
                'total_amount' => (string) $booking->total_amount,
            ]);

            return null;
        }

        return PlatformCharge::query()->firstOrCreate(
            ['booking_id' => $booking->id],
            [
                'tenant_id' => $tenant->id,
                'payment_id' => $payment?->id,
                'base_amount' => $booking->total_amount,
                'applied_rate' => $result['rate'],
                'amount' => $result['amount'],
                'currency' => $schedule->currency,
                'tier_from' => $result['tier_from'],
                'tier_to' => $result['tier_to'],
                'max_charge' => $schedule->max_charge,
                'was_capped' => $result['was_capped'],
                'schedule_scope' => $scope,
                'charged_at' => $booking->confirmed_at ?? now(),
            ],
        );
    }
}
