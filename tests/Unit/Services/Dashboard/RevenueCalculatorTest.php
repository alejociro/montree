<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Dashboard;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Services\Dashboard\PeriodFilter;
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

    public function test_series_groups_gross_revenue_by_day_on_short_periods(): void
    {
        $period = PeriodFilter::fromKey(
            PeriodFilter::KEY_LAST_7_DAYS,
            'UTC',
            Carbon::parse('2026-04-20 12:00:00'),
        );
        $booking = Booking::factory()->confirmed()->create();

        Payment::factory()->completed()->for($booking)->create([
            'amount' => 100,
            'processed_at' => Carbon::parse('2026-04-15 09:00:00'),
        ]);
        Payment::factory()->completed()->for($booking)->create([
            'amount' => 50.5,
            'processed_at' => Carbon::parse('2026-04-15 20:30:00'),
        ]);
        Payment::factory()->completed()->for($booking)->create([
            'amount' => 200,
            'processed_at' => Carbon::parse('2026-04-17 11:00:00'),
        ]);
        Payment::factory()->completed()->for($booking)->create([
            'amount' => 75,
            'processed_at' => Carbon::parse('2026-04-19 08:00:00'),
        ]);
        Payment::factory()->failed()->for($booking)->create([
            'amount' => 999,
            'processed_at' => Carbon::parse('2026-04-18 08:00:00'),
        ]);
        Payment::factory()->completed()->for($booking)->create([
            'amount' => 999,
            'processed_at' => Carbon::parse('2026-04-10 08:00:00'),
        ]);

        $breakdown = (new RevenueCalculator)->between(
            $this->tenant,
            $period->start,
            $period->end,
            $period->previousStart,
            $period->previousEnd,
        );

        $this->assertSame([
            ['date' => '2026-04-15', 'amount' => '150.50'],
            ['date' => '2026-04-17', 'amount' => '200.00'],
            ['date' => '2026-04-19', 'amount' => '75.00'],
        ], $breakdown->series);
    }

    public function test_series_groups_by_week_on_medium_periods(): void
    {
        $period = PeriodFilter::fromKey(
            PeriodFilter::KEY_LAST_90_DAYS,
            'UTC',
            Carbon::parse('2026-04-20 12:00:00'),
        );
        $booking = Booking::factory()->confirmed()->create();

        Payment::factory()->completed()->for($booking)->create([
            'amount' => 100,
            'processed_at' => Carbon::parse('2026-02-03 09:00:00'),
        ]);
        Payment::factory()->completed()->for($booking)->create([
            'amount' => 30,
            'processed_at' => Carbon::parse('2026-02-05 09:00:00'),
        ]);
        Payment::factory()->completed()->for($booking)->create([
            'amount' => 40,
            'processed_at' => Carbon::parse('2026-02-09 09:00:00'),
        ]);

        $series = (new RevenueCalculator)->grossSeries($period->start, $period->end);

        $this->assertSame([
            ['date' => '2026-02-02', 'amount' => '130.00'],
            ['date' => '2026-02-09', 'amount' => '40.00'],
        ], $series);
    }

    public function test_series_groups_by_month_on_long_periods(): void
    {
        $period = PeriodFilter::fromKey(
            PeriodFilter::KEY_THIS_YEAR,
            'UTC',
            Carbon::parse('2026-12-15 12:00:00'),
        );
        $booking = Booking::factory()->confirmed()->create();

        Payment::factory()->completed()->for($booking)->create([
            'amount' => 100,
            'processed_at' => Carbon::parse('2026-01-04 09:00:00'),
        ]);
        Payment::factory()->completed()->for($booking)->create([
            'amount' => 25,
            'processed_at' => Carbon::parse('2026-01-29 09:00:00'),
        ]);
        Payment::factory()->completed()->for($booking)->create([
            'amount' => 60,
            'processed_at' => Carbon::parse('2026-03-11 09:00:00'),
        ]);

        $series = (new RevenueCalculator)->grossSeries($period->start, $period->end);

        $this->assertSame([
            ['date' => '2026-01-01', 'amount' => '125.00'],
            ['date' => '2026-03-01', 'amount' => '60.00'],
        ], $series);
    }

    public function test_series_is_empty_when_period_has_no_completed_payments(): void
    {
        $period = PeriodFilter::fromKey(
            PeriodFilter::KEY_LAST_7_DAYS,
            'UTC',
            Carbon::parse('2026-04-20 12:00:00'),
        );

        $this->assertSame([], (new RevenueCalculator)->grossSeries($period->start, $period->end));
    }
}
