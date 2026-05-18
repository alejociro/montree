<?php

declare(strict_types=1);

namespace App\Data\Dashboard;

final readonly class RatingSummary
{
    public function __construct(
        public string $average,
        public int $count,
    ) {}
}
