<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Actions\Payment\RegisterManualPaymentAction;
use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

/**
 * El cobro de la plataforma no puede tumbar la liquidación del pago.
 *
 * `BookingSettlementService` corre dentro del `DB::transaction()` de quien
 * liquida, con la reserva bloqueada. Mientras el listener del cargo fuera
 * síncrono, cualquier fallo suyo revertía el pago entero: el dinero ya había
 * entrado y la reserva se quedaba sin confirmar.
 */
final class PlatformChargeFailureDoesNotBlockSettlementTest extends SuperAdminTestCase
{
    public function test_a_failing_platform_charge_leaves_the_payment_completed_and_the_booking_confirmed(): void
    {
        $tenant = $this->tenantCharging();
        $booking = $this->pendingBooking($tenant, '250.00');

        // Se rompe el cargo por donde se rompe de verdad: el INSERT en
        // `platform_charges`. Con el driver `sync` la excepción vuelve por el
        // mismo hilo, pero ya fuera de la transacción — que es el punto.
        Schema::drop('platform_charges');

        $this->assertThrows(fn () => $this->pay($booking, '250.00'), QueryException::class);

        $settled = $booking->fresh();
        $this->assertSame(BookingStatus::Confirmed, $settled?->status);
        $this->assertSame('250.00', $settled?->paid_amount);
        $this->assertNotNull($settled?->confirmed_at);
        $this->assertSame(PaymentStatus::Completed, Payment::query()->sole()->status);
    }

    /**
     * WHY: no hace falta esquema propio, el global (COP, sembrado por
     * migración) ya cubre cualquier monto y esta agencia opera en COP.
     */
    private function tenantCharging(): Tenant
    {
        $tenant = Tenant::factory()->create();

        TenantConfiguration::factory()->for($tenant)->create(['currency' => 'COP']);

        return $tenant;
    }

    private function pendingBooking(Tenant $tenant, string $total): Booking
    {
        $tenant->makeCurrent();

        $booking = Booking::factory()->create([
            'total_amount' => $total,
            'subtotal' => $total,
            'paid_amount' => '0.00',
        ]);

        Tenant::forgetCurrent();

        return $booking;
    }

    private function pay(Booking $booking, string $amount): void
    {
        $tenant = Tenant::query()->findOrFail($booking->tenant_id);
        $tenant->makeCurrent();

        try {
            app(RegisterManualPaymentAction::class)
                ->handle($booking, PaymentGateway::Cash, $amount, null, null);
        } finally {
            Tenant::forgetCurrent();
        }
    }
}
