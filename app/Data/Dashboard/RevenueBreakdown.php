<?php

declare(strict_types=1);

namespace App\Data\Dashboard;

final readonly class RevenueBreakdown
{
    /**
     * @param  list<array{date: string, amount: string}>  $series
     */
    public function __construct(
        public string $gross,
        public string $net,
        public string $previousGross,
        public ?float $growthPct,
        public string $currency,
        public array $series,
    ) {}
}
