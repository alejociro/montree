<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use App\Services\Platform\PlatformChargeCalculator;
use PHPUnit\Framework\TestCase;

class PlatformChargeCalculatorTest extends TestCase
{
    /**
     * @return array<int, array{from: string, to: string|null, rate: string}>
     */
    private static function exampleTiers(): array
    {
        return [
            ['from' => '0.00', 'to' => '500000.00', 'rate' => '9.00'],
            ['from' => '500000.00', 'to' => '5000000.00', 'rate' => '7.00'],
            ['from' => '5000000.00', 'to' => null, 'rate' => '5.00'],
        ];
    }

    /**
     * El tope es del ESQUEMA, no del rango: aplica sin importar en qué rango
     * cae la reserva.
     */
    public function test_the_schedule_cap_applies_regardless_of_the_tier(): void
    {
        $calculator = new PlatformChargeCalculator;

        $result = $calculator->calculateForTiers(self::exampleTiers(), '20000000.00', '250000.00');

        $this->assertNotNull($result);
        $this->assertSame('250000.00', $result['amount']);
        $this->assertTrue($result['was_capped']);
        $this->assertSame('5000000.00', $result['tier_from']);
        $this->assertNull($result['tier_to']);
    }

    public function test_a_million_charges_the_uncapped_rate(): void
    {
        $calculator = new PlatformChargeCalculator;

        $result = $calculator->calculateForTiers(self::exampleTiers(), '1000000.00', '250000.00');

        $this->assertNotNull($result);
        $this->assertSame('7.00', $result['rate']);
        $this->assertSame('70000.00', $result['amount']);
        $this->assertFalse($result['was_capped']);
    }

    /**
     * 4.000.000 × 7% = 280.000 > tope 250.000 → cobra el tope.
     */
    public function test_a_rate_that_exceeds_the_cap_is_capped(): void
    {
        $calculator = new PlatformChargeCalculator;

        $result = $calculator->calculateForTiers(self::exampleTiers(), '4000000.00', '250000.00');

        $this->assertNotNull($result);
        $this->assertSame('7.00', $result['rate']);
        $this->assertSame('250000.00', $result['amount']);
        $this->assertTrue($result['was_capped']);
    }

    public function test_a_null_cap_never_caps(): void
    {
        $calculator = new PlatformChargeCalculator;

        $result = $calculator->calculateForTiers(self::exampleTiers(), '20000000.00', null);

        $this->assertNotNull($result);
        $this->assertSame('5.00', $result['rate']);
        $this->assertSame('1000000.00', $result['amount']);
        $this->assertFalse($result['was_capped']);
    }

    public function test_a_booking_of_exactly_the_boundary_falls_in_the_next_tier(): void
    {
        $calculator = new PlatformChargeCalculator;

        $result = $calculator->calculateForTiers(self::exampleTiers(), '500000.00', null);

        $this->assertNotNull($result);
        $this->assertSame('7.00', $result['rate']);
        $this->assertSame('35000.00', $result['amount']);
    }

    public function test_a_booking_just_below_the_boundary_stays_in_the_first_tier(): void
    {
        $calculator = new PlatformChargeCalculator;

        $result = $calculator->calculateForTiers(self::exampleTiers(), '499999.99', null);

        $this->assertNotNull($result);
        $this->assertSame('9.00', $result['rate']);
    }

    public function test_a_rate_that_rounds_up_is_rounded_half_up(): void
    {
        $calculator = new PlatformChargeCalculator;

        $result = $calculator->calculateForTiers(
            [['from' => '0.00', 'to' => null, 'rate' => '3.00']],
            '10.17',
            null,
        );

        $this->assertNotNull($result);
        $this->assertSame('0.31', $result['amount']);
    }
}
