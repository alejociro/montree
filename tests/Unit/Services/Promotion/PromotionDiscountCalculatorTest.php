<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Promotion;

use App\Enums\PromotionType;
use App\Models\Promotion;
use App\Services\Promotion\PromotionDiscountCalculator;
use PHPUnit\Framework\TestCase;

class PromotionDiscountCalculatorTest extends TestCase
{
    private PromotionDiscountCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new PromotionDiscountCalculator;
    }

    public function test_calculates_percentage_discount(): void
    {
        $promotion = $this->makePromotion(PromotionType::Percentage, '10.00');

        $result = $this->calculator->calculate($promotion, '120000.00');

        $this->assertSame('12000.00', $result['discount']);
        $this->assertSame('108000.00', $result['total_after']);
    }

    public function test_calculates_fixed_discount(): void
    {
        $promotion = $this->makePromotion(PromotionType::Fixed, '5000.00');

        $result = $this->calculator->calculate($promotion, '120000.00');

        $this->assertSame('5000.00', $result['discount']);
        $this->assertSame('115000.00', $result['total_after']);
    }

    public function test_caps_discount_at_max_discount(): void
    {
        $promotion = $this->makePromotion(PromotionType::Percentage, '50.00', maxDiscount: '20000.00');

        $result = $this->calculator->calculate($promotion, '100000.00');

        $this->assertSame('20000.00', $result['discount']);
        $this->assertSame('80000.00', $result['total_after']);
    }

    public function test_enforces_one_dollar_minimum_total(): void
    {
        $promotion = $this->makePromotion(PromotionType::Percentage, '100.00');

        $result = $this->calculator->calculate($promotion, '50.00');

        $this->assertSame('49.00', $result['discount']);
        $this->assertSame('1.00', $result['total_after']);
    }

    public function test_discount_never_exceeds_subtotal_with_fixed_value(): void
    {
        $promotion = $this->makePromotion(PromotionType::Fixed, '999999.00');

        $result = $this->calculator->calculate($promotion, '500.00');

        $this->assertSame('499.00', $result['discount']);
        $this->assertSame('1.00', $result['total_after']);
    }

    private function makePromotion(PromotionType $type, string $value, ?string $maxDiscount = null): Promotion
    {
        $promotion = new Promotion;
        $promotion->setRawAttributes([
            'type' => $type->value,
            'value' => $value,
            'max_discount' => $maxDiscount,
        ]);
        $promotion->setRelations([]);

        return $promotion;
    }
}
