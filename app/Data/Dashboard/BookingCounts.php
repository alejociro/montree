<?php

declare(strict_types=1);

namespace App\Data\Dashboard;

final readonly class BookingCounts
{
    public function __construct(
        public int $total,
        public int $confirmed,
        public int $pendingPayment,
        public int $cancelled,
        public int $previousTotal,
        public ?float $growthPct,
    ) {}
}
