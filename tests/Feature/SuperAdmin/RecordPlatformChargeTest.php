<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Actions\Payment\RegisterManualPaymentAction;
use App\Enums\CommissionType;
use App\Enums\PaymentGateway;
use App\Models\Booking;
use App\Models\PlatformCharge;
use App\Models\Tenant;
use App\Models\TenantConfiguration;

class RecordPlatformChargeTest extends SuperAdminTestCase
{
    public function test_a_percentage_commission_charges_over_the_booking_total(): void
    {
        $tenant = $this->tenantCharging(CommissionType::Percentage, '10');
        $booking = $this->pendingBooking($tenant, '250.00');

        $this->pay($booking, '250.00');

        $charge = PlatformCharge::query()->where('booking_id', $booking->id)->sole();
        $this->assertSame('25.00', $charge->amount);
        $this->assertSame('250.00', $charge->base_amount);
        $this->assertSame(CommissionType::Percentage, $charge->commission_type);
        $this->assertSame('COP', $charge->currency);
    }

    public function test_a_fixed_commission_charges_the_configured_amount(): void
    {
        $tenant = $this->tenantCharging(CommissionType::Fixed, '4.50');
        $booking = $this->pendingBooking($tenant, '900.00');

        $this->pay($booking, '900.00');

        $this->assertSame('4.50', PlatformCharge::query()->sole()->amount);
    }

    public function test_a_tenant_without_commission_generates_no_charge(): void
    {
        $tenant = Tenant::factory()->create();
        TenantConfiguration::factory()->for($tenant)->create();
        $booking = $this->pendingBooking($tenant, '100.00');

        $this->pay($booking, '100.00');

        $this->assertSame(0, PlatformCharge::query()->count());
    }

    public function test_two_partial_payments_on_the_same_booking_charge_only_once(): void
    {
        $tenant = $this->tenantCharging(CommissionType::Percentage, '10');
        $booking = $this->pendingBooking($tenant, '200.00');

        $this->pay($booking, '120.00');
        $this->pay($booking->fresh(), '80.00');

        $this->assertSame(1, PlatformCharge::query()->count());
        $this->assertSame('20.00', PlatformCharge::query()->sole()->amount);
    }

    public function test_changing_the_rate_afterwards_does_not_recalculate_past_charges(): void
    {
        $tenant = $this->tenantCharging(CommissionType::Percentage, '10');
        $booking = $this->pendingBooking($tenant, '100.00');

        $this->pay($booking, '100.00');

        $tenant->update(['commission_value' => '50']);

        $charge = PlatformCharge::query()->sole();
        $this->assertSame('10.00', $charge->amount);
        $this->assertSame('10.00', $charge->applied_value);
    }

    private function tenantCharging(CommissionType $type, string $value): Tenant
    {
        $tenant = Tenant::factory()->create([
            'commission_type' => $type,
            'commission_value' => $value,
        ]);

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
