<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PlatformCharge;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

class DashboardPageTest extends SuperAdminTestCase
{
    public function test_the_dashboard_ships_every_total_as_a_prop(): void
    {
        Tenant::factory()->count(2)->create();
        Tenant::factory()->basic()->create();

        $this->actingAs($this->superAdmin())
            ->get($this->platformUrl('/super-admin/dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('SuperAdmin/Dashboard')
                ->where('totals.tenants', 3)
                ->hasAll([
                    'totals.active_tenants',
                    'totals.users',
                    'totals.bookings_this_month',
                    'totals.revenue_this_month',
                    'totals.earnings_this_month',
                    'growth.tenants_new_this_month',
                    'growth.bookings_growth_pct',
                    'plan_distribution.basic',
                    'plan_distribution.professional',
                    'plan_distribution.enterprise',
                ]));
    }

    public function test_earnings_are_the_platform_charges_of_the_month(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->makeCurrent();
        $booking = Booking::factory()->create();
        Tenant::forgetCurrent();

        PlatformCharge::factory()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $booking->id,
            'amount' => '12.34',
            'charged_at' => CarbonImmutable::now(),
        ]);

        $this->actingAs($this->superAdmin())
            ->get($this->platformUrl('/super-admin/dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('totals.earnings_this_month', '12.34'));
    }

    public function test_the_charts_cover_twelve_months_and_split_revenue_per_tenant(): void
    {
        $now = CarbonImmutable::now();

        $first = Tenant::factory()->create(['name' => 'Eco Uno', 'created_at' => $now->subMonths(2)]);
        $second = Tenant::factory()->create(['name' => 'Eco Dos', 'created_at' => $now]);

        $this->completedPaymentFor($first, '100.00', $now->subMonths(2));
        $this->completedPaymentFor($second, '250.00', $now);

        $this->actingAs($this->superAdmin())
            ->get($this->platformUrl('/super-admin/dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('charts.tenants_per_month.points', 12)
                ->has('charts.earnings_per_month.points', 12)
                ->where('charts.earnings_per_month.total', '0.00')
                ->has('charts.revenue_per_tenant.months', 6)
                ->has('charts.revenue_per_tenant.series', 2)
                ->where('charts.revenue_per_tenant.series.0.tenant', 'Eco Dos')
                ->has('charts.revenue_per_tenant.series.0.values', 6)
                ->etc());
    }

    public function test_a_regular_user_cannot_open_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get($this->platformUrl('/super-admin/dashboard'))
            ->assertForbidden();
    }

    public function test_an_anonymous_visitor_is_sent_to_the_login(): void
    {
        $this->get($this->platformUrl('/super-admin/dashboard'))->assertRedirect(route('login'));
    }

    private function completedPaymentFor(Tenant $tenant, string $amount, CarbonImmutable $processedAt): void
    {
        $tenant->makeCurrent();

        Payment::factory()->create([
            'status' => PaymentStatus::Completed,
            'processed_at' => $processedAt,
            'amount' => $amount,
        ]);

        Tenant::forgetCurrent();
    }
}
