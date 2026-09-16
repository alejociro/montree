<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PlatformCharge;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

/**
 * Dos agencias en monedas distintas no se suman: el panel de plataforma agrupa
 * por moneda y no convierte nada (spec §H).
 */
final class PlatformTotalsByCurrencyTest extends SuperAdminTestCase
{
    public function test_revenue_of_the_month_is_grouped_by_currency(): void
    {
        $this->completedPayment($this->tenantIn('COP'), '100000.00', 'COP');
        $this->completedPayment($this->tenantIn('USD'), '250.00', 'USD');

        $this->actingAs($this->superAdmin())
            ->get($this->platformUrl('/super-admin/dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('totals.revenue_this_month', [
                ['currency' => 'COP', 'amount' => '100000.00'],
                ['currency' => 'USD', 'amount' => '250.00'],
            ])->etc());
    }

    public function test_earnings_of_the_month_are_grouped_by_currency(): void
    {
        $this->charge($this->tenantIn('COP'), '4000.00', 'COP');
        $this->charge($this->tenantIn('USD'), '9.00', 'USD');

        $this->actingAs($this->superAdmin())
            ->get($this->platformUrl('/super-admin/dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('totals.earnings_this_month', [
                ['currency' => 'COP', 'amount' => '4000.00'],
                ['currency' => 'USD', 'amount' => '9.00'],
            ])->etc());
    }

    public function test_the_earnings_chart_carries_one_series_per_currency(): void
    {
        $this->charge($this->tenantIn('COP'), '4000.00', 'COP');
        $this->charge($this->tenantIn('USD'), '9.00', 'USD');

        $this->actingAs($this->superAdmin())
            ->get($this->platformUrl('/super-admin/dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('charts.earnings_per_month.series', 2)
                ->where('charts.earnings_per_month.series.0.currency', 'COP')
                ->where('charts.earnings_per_month.series.0.total', '4000.00')
                ->where('charts.earnings_per_month.series.1.total', '9.00')
                ->etc());
    }

    public function test_a_single_currency_stays_a_single_entry(): void
    {
        $this->completedPayment($this->tenantIn('COP'), '100.00', 'COP');
        $this->completedPayment($this->tenantIn('COP'), '50.00', 'COP');

        $this->actingAs($this->superAdmin())
            ->get($this->platformUrl('/super-admin/dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('totals.revenue_this_month', [
                ['currency' => 'COP', 'amount' => '150.00'],
            ])->etc());
    }

    private function tenantIn(string $currency): Tenant
    {
        $tenant = Tenant::factory()->create();
        TenantConfiguration::factory()->for($tenant)->create(['currency' => $currency]);

        return $tenant;
    }

    private function completedPayment(Tenant $tenant, string $amount, string $currency): void
    {
        $tenant->makeCurrent();

        Payment::factory()->create([
            'status' => PaymentStatus::Completed,
            'processed_at' => CarbonImmutable::now(),
            'amount' => $amount,
            'currency' => $currency,
        ]);

        Tenant::forgetCurrent();
    }

    private function charge(Tenant $tenant, string $amount, string $currency): void
    {
        $tenant->makeCurrent();
        $booking = Booking::factory()->create();
        Tenant::forgetCurrent();

        PlatformCharge::factory()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $booking->id,
            'amount' => $amount,
            'currency' => $currency,
            'charged_at' => CarbonImmutable::now(),
        ]);
    }
}
