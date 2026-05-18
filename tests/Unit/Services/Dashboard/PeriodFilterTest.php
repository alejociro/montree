<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Dashboard;

use App\Services\Dashboard\PeriodFilter;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PeriodFilterTest extends TestCase
{
    public function test_resolves_last_30_days_with_previous_window(): void
    {
        $reference = Carbon::parse('2026-05-17 12:00:00', 'UTC');

        $filter = PeriodFilter::fromKey(PeriodFilter::KEY_LAST_30_DAYS, 'UTC', $reference);

        $this->assertSame(PeriodFilter::KEY_LAST_30_DAYS, $filter->key);
        $this->assertSame('2026-04-18 00:00:00', $filter->start->toDateTimeString());
        $this->assertSame('2026-05-17 23:59:59', $filter->end->toDateTimeString());
        $this->assertSame('2026-03-19 00:00:00', $filter->previousStart->toDateTimeString());
        $this->assertSame('2026-04-17 23:59:59', $filter->previousEnd->toDateTimeString());
    }

    public function test_resolves_last_7_days_correctly(): void
    {
        $reference = Carbon::parse('2026-05-17 12:00:00', 'UTC');

        $filter = PeriodFilter::fromKey(PeriodFilter::KEY_LAST_7_DAYS, 'UTC', $reference);

        $this->assertSame('2026-05-11 00:00:00', $filter->start->toDateTimeString());
        $this->assertSame('2026-05-17 23:59:59', $filter->end->toDateTimeString());
    }

    public function test_resolves_this_month_with_previous_month(): void
    {
        $reference = Carbon::parse('2026-05-17 12:00:00', 'UTC');

        $filter = PeriodFilter::fromKey(PeriodFilter::KEY_THIS_MONTH, 'UTC', $reference);

        $this->assertSame('2026-05-01 00:00:00', $filter->start->toDateTimeString());
        $this->assertSame('2026-04-01 00:00:00', $filter->previousStart->toDateTimeString());
        $this->assertSame('2026-04-30 23:59:59', $filter->previousEnd->toDateTimeString());
    }

    public function test_resolves_last_month_correctly(): void
    {
        $reference = Carbon::parse('2026-05-17 12:00:00', 'UTC');

        $filter = PeriodFilter::fromKey(PeriodFilter::KEY_LAST_MONTH, 'UTC', $reference);

        $this->assertSame('2026-04-01 00:00:00', $filter->start->toDateTimeString());
        $this->assertSame('2026-04-30 23:59:59', $filter->end->toDateTimeString());
    }

    public function test_resolves_this_year_with_previous_year(): void
    {
        $reference = Carbon::parse('2026-05-17 12:00:00', 'UTC');

        $filter = PeriodFilter::fromKey(PeriodFilter::KEY_THIS_YEAR, 'UTC', $reference);

        $this->assertSame('2026-01-01 00:00:00', $filter->start->toDateTimeString());
        $this->assertSame('2025-01-01 00:00:00', $filter->previousStart->toDateTimeString());
        $this->assertSame('2025-12-31 23:59:59', $filter->previousEnd->toDateTimeString());
    }

    public function test_respects_provided_timezone(): void
    {
        $reference = Carbon::parse('2026-05-17 04:00:00', 'UTC');

        $filter = PeriodFilter::fromKey(PeriodFilter::KEY_LAST_7_DAYS, 'America/Bogota', $reference);

        $this->assertSame('America/Bogota', $filter->start->timezone->getName());
        $this->assertSame('2026-05-10 00:00:00', $filter->start->toDateTimeString());
    }

    public function test_throws_when_key_unsupported(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PeriodFilter::fromKey('unknown_period', 'UTC');
    }
}
