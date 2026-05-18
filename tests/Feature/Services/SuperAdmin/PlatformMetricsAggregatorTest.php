<?php

declare(strict_types=1);

namespace Tests\Feature\Services\SuperAdmin;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\SuperAdmin\PlatformMetricsAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PlatformMetricsAggregatorTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_collects_totals_across_all_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->basic()->create();

        $tenantA->makeCurrent();
        Booking::factory()->count(2)->create();

        $tenantB->makeCurrent();
        $bookings = Booking::factory()->count(2)->create();
        foreach ($bookings as $booking) {
            Payment::factory()->create([
                'booking_id' => $booking->id,
                'status' => PaymentStatus::Completed,
                'processed_at' => Carbon::now(),
                'amount' => '100.00',
            ]);
        }

        Tenant::forgetCurrent();

        $aggregator = app(PlatformMetricsAggregator::class);
        $metrics = $aggregator->collect();

        $this->assertSame(2, $metrics->totalTenants);
        $this->assertSame(4, $metrics->bookingsThisMonth);
        $this->assertSame('200.00', $metrics->revenueThisMonth);
        $this->assertSame(1, $metrics->planDistribution['basic']);
        $this->assertSame(1, $metrics->planDistribution['professional']);
    }

    public function test_skips_uncompleted_payments_from_revenue(): void
    {
        $tenant = Tenant::factory()->create();

        $tenant->makeCurrent();
        Payment::factory()->create([
            'status' => PaymentStatus::Pending,
            'processed_at' => Carbon::now(),
            'amount' => '999.00',
        ]);
        Payment::factory()->create([
            'status' => PaymentStatus::Completed,
            'processed_at' => Carbon::now(),
            'amount' => '50.00',
        ]);
        Tenant::forgetCurrent();

        $metrics = app(PlatformMetricsAggregator::class)->collect();

        $this->assertSame('50.00', $metrics->revenueThisMonth);
    }

    public function test_stats_for_tenant_returns_isolated_counts(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $tenantA->makeCurrent();
        Booking::factory()->count(3)->create();

        $tenantB->makeCurrent();
        Booking::factory()->count(7)->create();

        Tenant::forgetCurrent();

        $stats = app(PlatformMetricsAggregator::class)->statsForTenant($tenantA);

        $this->assertSame(3, $stats['bookings_count_30d']);
    }
}
