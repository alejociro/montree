<?php

declare(strict_types=1);

namespace App\Services\Promotion;

use App\Enums\PromotionType;
use App\Models\Promotion;

final class PromotionDiscountCalculator
{
    private const MIN_TOTAL = '1.00';

    /**
     * @return array{discount: string, total_after: string}
     */
    public function calculate(Promotion $promotion, string $subtotal): array
    {
        $subtotalFloat = (float) $subtotal;
        $discountFloat = $this->rawDiscount($promotion, $subtotalFloat);

        if ($promotion->max_discount !== null) {
            $discountFloat = min($discountFloat, (float) $promotion->max_discount);
        }

        $discountFloat = min($discountFloat, $subtotalFloat);
        $totalFloat = $subtotalFloat - $discountFloat;

        if ($totalFloat < (float) self::MIN_TOTAL) {
            $totalFloat = (float) self::MIN_TOTAL;
            $discountFloat = max(0.0, $subtotalFloat - $totalFloat);
        }

        return [
            'discount' => number_format($discountFloat, 2, '.', ''),
            'total_after' => number_format($totalFloat, 2, '.', ''),
        ];
    }

    private function rawDiscount(Promotion $promotion, float $subtotal): float
    {
        $value = (float) $promotion->value;

        return match ($promotion->type) {
            PromotionType::Percentage => round($subtotal * ($value / 100), 2),
            PromotionType::Fixed => $value,
        };
    }
}
