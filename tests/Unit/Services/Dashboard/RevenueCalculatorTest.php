<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Dashboard;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Services\Dashboard\RevenueCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RevenueCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        TenantConfiguration::factory()->for($this->tenant)->create(['currency' => 'COP']);
        $this->tenant->makeCurrent();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_sums_only_completed_payments_inside_range(): void
    {
        $start = Carbon::parse('2026-04-01 00:00:00');
        $end = Carbon::parse('2026-04-30 23:59:59');
        $booking = Booking::factory()->confirmed()->create();

        Payment::factory()->completed()->for($booking)->create([
            'amount' => 100,
            'processed_at' => Carbon::parse('2026-04-10 12:00:00'),
        ]);
        Payment::factory()->completed()->for($booking)->create([
            'amount' => 250,
            'processed_at' => Carbon::parse('2026-04-20 12:00:00'),
        ]);
        Payment::factory()->failed()->for($booking)->create([
            'amount' => 999,
            'processed_at' => Carbon::parse('2026-04-15 12:00:00'),
        ]);
        Payment::factory()->completed()->for($booking)->create([
            'amount' => 500,
            'processed_at' => Carbon::parse('2026-05-01 12:00:00'),
        ]);

        $previousStart = Carbon::parse('2026-03-01 00:00:00');
        $previousEnd = Carbon::parse('2026-03-31 23:59:59');

        $breakdown = (new RevenueCalculator)->between($this->tenant, $start, $end, $previousStart, $previousEnd);

        $this->assertSame('350.00', $breakdown->gross);
        $this->assertSame('350.00', $breakdown->net);
        $this->assertSame('0.00', $breakdown->previousGross);
        $this->assertNull($breakdown->growthPct);
        $this->assertSame('COP', $breakdown->currency);
    }

    public function test_calculates_growth_pct_when_previous_has_revenue(): void
    {
        $start = Carbon::parse('2026-04-01 00:00:00');
        $end = Carbon::parse('2026-04-30 23:59:59');
        $booking = Booking::factory()->confirmed()->create();

        Payment::factory()->completed()->for($booking)->create([
            'amount' => 200,
            'processed_at' => Carbon::parse('2026-04-15 12:00:00'),
        ]);

        Payment::factory()->completed()->for($booking)->create([
            'amount' => 100,
            'processed_at' => Carbon::parse('2026-03-15 12:00:00'),
        ]);

        $breakdown = (new RevenueCalculator)->between(
            $this->tenant,
            $start,
            $end,
            Carbon::parse('2026-03-01 00:00:00'),
            Carbon::parse('2026-03-31 23:59:59'),
        );

        $this->assertSame('200.00', $breakdown->gross);
        $this->assertSame('100.00', $breakdown->previousGross);
        $this->assertSame(100.0, $breakdown->growthPct);
    }

    public function test_subtracts_refunds_from_net_revenue(): void
    {
        $start = Carbon::parse('2026-04-01 00:00:00');
        $end = Carbon::parse('2026-04-30 23:59:59');
        $booking = Booking::factory()->confirmed()->create();

        Payment::factory()->completed()->for($booking)->create([
            'amount' => 500,
            'processed_at' => Carbon::parse('2026-04-10 12:00:00'),
        ]);

        Payment::factory()->for($booking)->create([
            'amount' => 500,
            'status' => PaymentStatus::Refunded->value,
            'refunded_amount' => 200,
            'refunded_at' => Carbon::parse('2026-04-15 12:00:00'),
        ]);

        $breakdown = (new RevenueCalculator)->between(
            $this->tenant,
            $start,
            $end,
            Carbon::parse('2026-03-01 00:00:00'),
            Carbon::parse('2026-03-31 23:59:59'),
        );

        $this->assertSame('500.00', $breakdown->gross);
        $this->assertSame('300.00', $breakdown->net);
    }
}
