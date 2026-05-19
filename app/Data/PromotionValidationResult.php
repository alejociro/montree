<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Promotion;

final readonly class PromotionValidationResult
{
    public function __construct(
        public Promotion $promotion,
        public string $discount,
        public string $totalAfter,
    ) {}
}
