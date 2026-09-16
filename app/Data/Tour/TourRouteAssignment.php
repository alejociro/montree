<?php

declare(strict_types=1);

namespace App\Data\Tour;

final readonly class TourRouteAssignment
{
    public function __construct(
        public int $routeId,
        public bool $isDefault,
    ) {}
}
