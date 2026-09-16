<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\MonthlySeries;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class MonthlySeriesTest extends TestCase
{
    public function test_months_lists_every_month_in_the_window_inclusive(): void
    {
        $months = MonthlySeries::months(
            CarbonImmutable::parse('2026-11-20'),
            CarbonImmutable::parse('2027-02-03'),
        );

        $this->assertSame(['2026-11', '2026-12', '2027-01', '2027-02'], $months);
    }

    public function test_months_returns_a_single_entry_when_the_window_is_one_month(): void
    {
        $months = MonthlySeries::months(
            CarbonImmutable::parse('2026-09-01'),
            CarbonImmutable::parse('2026-09-30'),
        );

        $this->assertSame(['2026-09'], $months);
    }

    public function test_sum_adds_amounts_per_month_without_float_drift(): void
    {
        $totals = MonthlySeries::sum([
            [CarbonImmutable::parse('2026-09-02'), '0.10'],
            [CarbonImmutable::parse('2026-09-28'), '0.20'],
            [CarbonImmutable::parse('2026-10-01'), '5'],
            [null, '999'],
        ]);

        $this->assertSame(['2026-09' => '0.30', '2026-10' => '5.00'], $totals);
    }

    public function test_count_buckets_dates_per_month(): void
    {
        $totals = MonthlySeries::count([
            CarbonImmutable::parse('2026-09-02'),
            CarbonImmutable::parse('2026-09-28'),
            CarbonImmutable::parse('2026-10-01'),
            null,
        ]);

        $this->assertSame(['2026-09' => 2, '2026-10' => 1], $totals);
    }

    public function test_points_fills_the_months_without_data_with_the_default(): void
    {
        $points = MonthlySeries::points(['2026-09', '2026-10'], ['2026-10' => '5.00'], '0.00');

        $this->assertSame('2026-09', $points[0]['month']);
        $this->assertSame('0.00', $points[0]['value']);
        $this->assertSame('5.00', $points[1]['value']);
        $this->assertNotSame('', $points[0]['label']);
    }
}
