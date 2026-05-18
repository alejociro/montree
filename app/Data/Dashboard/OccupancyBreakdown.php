<?php

declare(strict_types=1);

namespace App\Data\Dashboard;

final readonly class OccupancyBreakdown
{
    public function __construct(
        public int $upcomingDatesCount,
        public int $totalCapacity,
        public int $bookedSeats,
        public ?float $occupancyPct,
    ) {}
}
