<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Actions\Payment\RegisterManualPaymentAction;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Booking;
use App\Models\CommissionSchedule;
use App\Models\Payment;
use App\Models\PlatformCharge;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use Illuminate\Support\Facades\Log;
use Tests\Support\FakeCheckout;

class RecordPlatformChargeTest extends SuperAdminTestCase
{
    public function test_a_percentage_commission_charges_over_the_booking_total(): void
    {
        $tenant = $this->tenantWithOwnSchedule('10', '250.00');
        $booking = $this->pendingBooking($tenant, '250.00');

        $this->pay($booking, '250.00');

        $charge = PlatformCharge::query()->where('booking_id', $booking->id)->sole();
        $this->assertSame('25.00', $charge->amount);
        $this->assertSame('250.00', $charge->base_amount);
        $this->assertSame('10.00', $charge->applied_rate);
        $this->assertSame('COP', $charge->currency);
        $this->assertSame('tenant', $charge->schedule_scope);
        $this->assertFalse($charge->was_capped);
    }

    /**
     * El tope es del ESQUEMA, no del rango: nunca se cobra más que él sin
     * importar en qué rango cae la reserva.
     */
    public function test_a_schedule_cap_never_charges_more_than_the_cap(): void
    {
        $tenant = Tenant::factory()->create();
        TenantConfiguration::factory()->for($tenant)->create(['currency' => 'COP']);
        CommissionSchedule::factory()->for($tenant)->withTiers([
            ['from' => '0.00', 'to' => null, 'rate' => '5.00'],
        ])->withMaxCharge('300000.00')->create();

        $booking = $this->pendingBooking($tenant, '10000000.00');
        $this->pay($booking, '10000000.00');

        $charge = PlatformCharge::query()->sole();
        $this->assertSame('300000.00', $charge->amount);
        $this->assertTrue($charge->was_capped);
        $this->assertSame('300000.00', $charge->max_charge);
    }

    public function test_a_null_cap_never_caps_the_charge(): void
    {
        $tenant = Tenant::factory()->create();
        TenantConfiguration::factory()->for($tenant)->create(['currency' => 'COP']);
        CommissionSchedule::factory()->for($tenant)->withTiers([
            ['from' => '0.00', 'to' => null, 'rate' => '5.00'],
        ])->withMaxCharge(null)->create();

        $booking = $this->pendingBooking($tenant, '10000000.00');
        $this->pay($booking, '10000000.00');

        $charge = PlatformCharge::query()->sole();
        $this->assertSame('500000.00', $charge->amount);
        $this->assertFalse($charge->was_capped);
        $this->assertNull($charge->max_charge);
    }

    public function test_a_tenant_without_its_own_schedule_falls_back_to_global(): void
    {
        CommissionSchedule::query()->whereNull('tenant_id')->delete();
        CommissionSchedule::factory()->global()->withTiers([
            ['from' => '0.00', 'to' => null, 'rate' => '8.00'],
        ])->create(['currency' => 'COP']);

        $tenant = Tenant::factory()->create();
        TenantConfiguration::factory()->for($tenant)->create(['currency' => 'COP']);
        $booking = $this->pendingBooking($tenant, '100.00');

        $this->pay($booking, '100.00');

        $charge = PlatformCharge::query()->sole();
        $this->assertSame('8.00', $charge->amount);
        $this->assertSame('global', $charge->schedule_scope);
    }

    public function test_a_currency_mismatch_skips_the_charge_and_logs_a_warning(): void
    {
        Log::spy();

        CommissionSchedule::query()->whereNull('tenant_id')->update(['currency' => 'COP']);

        $tenant = Tenant::factory()->create();
        TenantConfiguration::factory()->for($tenant)->create(['currency' => 'USD']);
        $booking = $this->pendingBooking($tenant, '100.00');

        $this->pay($booking, '100.00');

        $this->assertSame(0, PlatformCharge::query()->count());
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_two_partial_payments_on_the_same_booking_charge_only_once(): void
    {
        $tenant = $this->tenantWithOwnSchedule('10', '200.00');
        $booking = $this->pendingBooking($tenant, '200.00');

        $this->pay($booking, '120.00');
        $this->pay($booking->fresh(), '80.00');

        $this->assertSame(1, PlatformCharge::query()->count());
        $this->assertSame('20.00', PlatformCharge::query()->sole()->amount);
    }

    public function test_changing_the_schedule_afterwards_does_not_recalculate_past_charges(): void
    {
        $tenant = $this->tenantWithOwnSchedule('10', '100.00');
        $booking = $this->pendingBooking($tenant, '100.00');

        $this->pay($booking, '100.00');

        CommissionSchedule::query()->where('tenant_id', $tenant->id)->update([
            'tiers' => [['from' => '0.00', 'to' => null, 'rate' => '50.00']],
        ]);

        $charge = PlatformCharge::query()->sole();
        $this->assertSame('10.00', $charge->amount);
        $this->assertSame('10.00', $charge->applied_rate);
    }

    /**
     * El cobro no puede depender de por dónde entró la plata: el pago manual y la
     * pasarela liquidan por el mismo `BookingSettlementService`, y desde que el
     * listener es `ShouldQueue` + `afterCommit` ese camino pasa por la cola.
     */
    public function test_a_gateway_payment_records_the_charge_too(): void
    {
        config([
            'placetopay.login' => 'platform-login',
            'placetopay.tran_key' => 'platform-tran-key',
            'placetopay.environments.test' => 'https://checkout.test',
            'placetopay.retry.attempts' => 1,
        ]);
        $checkout = FakeCheckout::fake();

        $tenant = $this->tenantWithOwnSchedule('10', '250.00', [
            'slug' => 'demo',
            'domain' => 'demo.montree.test',
        ]);
        $booking = $this->pendingBooking($tenant, '250.00');
        $payment = $this->gatewayPayment($tenant, $booking);

        $checkout->queryApproved('250.00');

        $this->postJson('http://demo.montree.test/payments/notification', [
            'requestId' => '12345',
            'reference' => 'MTR-1',
            'signature' => sha1('12345APPROVED'.FakeCheckout::TRANSACTION_DATE.'platform-tran-key'),
            'status' => [
                'status' => 'APPROVED',
                'reason' => '00',
                'message' => 'Aprobada',
                'date' => FakeCheckout::TRANSACTION_DATE,
            ],
        ])->assertOk();

        $charge = PlatformCharge::query()->sole();
        $this->assertSame('25.00', $charge->amount);
        $this->assertSame($booking->id, $charge->booking_id);
        $this->assertSame($payment->id, $charge->payment_id);
        $this->assertSame('COP', $charge->currency);
    }

    private function gatewayPayment(Tenant $tenant, Booking $booking): Payment
    {
        return Payment::query()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $booking->id,
            'gateway' => PaymentGateway::PlaceToPay,
            'request_id' => '12345',
            'amount' => '250.00',
            'currency' => 'COP',
            'type' => PaymentType::Full,
            'status' => PaymentStatus::Processing,
            'reference' => 'MTR-1',
            'process_url' => 'https://checkout.test/session/12345/xyz',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function tenantWithOwnSchedule(string $rate, string $upTo, array $attributes = []): Tenant
    {
        $tenant = Tenant::factory()->create($attributes);

        TenantConfiguration::factory()->for($tenant)->create(['currency' => 'COP']);

        CommissionSchedule::factory()->for($tenant)->withTiers([
            ['from' => '0.00', 'to' => null, 'rate' => $rate],
        ])->create(['currency' => 'COP']);

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

        app(RegisterManualPaymentAction::class)->handle(
            $booking,
            PaymentGateway::Cash,
            $amount,
            null,
            null,
        );

        Tenant::forgetCurrent();
    }
}
