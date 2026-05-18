<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use DateTimeZone;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final readonly class PeriodFilter
{
    public const KEY_LAST_7_DAYS = 'last_7_days';

    public const KEY_LAST_30_DAYS = 'last_30_days';

    public const KEY_LAST_90_DAYS = 'last_90_days';

    public const KEY_THIS_MONTH = 'this_month';

    public const KEY_LAST_MONTH = 'last_month';

    public const KEY_THIS_YEAR = 'this_year';

    public const SUPPORTED_KEYS = [
        self::KEY_LAST_7_DAYS,
        self::KEY_LAST_30_DAYS,
        self::KEY_LAST_90_DAYS,
        self::KEY_THIS_MONTH,
        self::KEY_LAST_MONTH,
        self::KEY_THIS_YEAR,
    ];

    public function __construct(
        public string $key,
        public Carbon $start,
        public Carbon $end,
        public Carbon $previousStart,
        public Carbon $previousEnd,
        public string $timezone,
    ) {}

    public static function fromKey(string $key, string $timezone, ?Carbon $reference = null): self
    {
        if (! in_array($key, self::SUPPORTED_KEYS, true)) {
            throw new InvalidArgumentException("Unsupported period key: {$key}.");
        }

        $tz = new DateTimeZone($timezone);
        $now = ($reference ?? Carbon::now())->copy()->setTimezone($tz);

        [$start, $end] = self::resolveRange($key, $now);
        [$previousStart, $previousEnd] = self::resolvePreviousRange($key, $start, $end);

        return new self($key, $start, $end, $previousStart, $previousEnd, $timezone);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function resolveRange(string $key, Carbon $now): array
    {
        return match ($key) {
            self::KEY_LAST_7_DAYS => [
                $now->copy()->subDays(6)->startOfDay(),
                $now->copy()->endOfDay(),
            ],
            self::KEY_LAST_30_DAYS => [
                $now->copy()->subDays(29)->startOfDay(),
                $now->copy()->endOfDay(),
            ],
            self::KEY_LAST_90_DAYS => [
                $now->copy()->subDays(89)->startOfDay(),
                $now->copy()->endOfDay(),
            ],
            self::KEY_THIS_MONTH => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfDay(),
            ],
            self::KEY_LAST_MONTH => [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            self::KEY_THIS_YEAR => [
                $now->copy()->startOfYear(),
                $now->copy()->endOfDay(),
            ],
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function resolvePreviousRange(string $key, Carbon $start, Carbon $end): array
    {
        if ($key === self::KEY_THIS_MONTH) {
            $previousStart = $start->copy()->subMonthNoOverflow()->startOfMonth();
            $previousEnd = $start->copy()->subMonthNoOverflow()->endOfMonth();

            return [$previousStart, $previousEnd];
        }

        if ($key === self::KEY_LAST_MONTH) {
            $previousStart = $start->copy()->subMonthNoOverflow()->startOfMonth();
            $previousEnd = $start->copy()->subMonthNoOverflow()->endOfMonth();

            return [$previousStart, $previousEnd];
        }

        if ($key === self::KEY_THIS_YEAR) {
            $previousStart = $start->copy()->subYear()->startOfYear();
            $previousEnd = $start->copy()->subYear()->endOfYear();

            return [$previousStart, $previousEnd];
        }

        $diffSeconds = $start->diffInSeconds($end);
        $previousEnd = $start->copy()->subSecond();
        $previousStart = $previousEnd->copy()->subSeconds((int) $diffSeconds);

        return [$previousStart, $previousEnd];
    }
}
