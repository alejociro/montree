<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use App\Enums\CommissionType;
use App\Services\Platform\PlatformChargeCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PlatformChargeCalculatorTest extends TestCase
{
    /**
     * @return array<string, array{0: CommissionType, 1: string, 2: string, 3: string}>
     */
    public static function chargeProvider(): array
    {
        return [
            'porcentaje redondo' => [CommissionType::Percentage, '10', '250.00', '25.00'],
            'porcentaje con decimales' => [CommissionType::Percentage, '7.5', '133.33', '10.00'],
            'porcentaje que redondea al alza' => [CommissionType::Percentage, '3', '10.17', '0.31'],
            'porcentaje cero' => [CommissionType::Percentage, '0', '999.99', '0.00'],
            'monto fijo' => [CommissionType::Fixed, '4.50', '999.99', '4.50'],
            'monto fijo mayor que la reserva' => [CommissionType::Fixed, '100.00', '20.00', '100.00'],
        ];
    }

    #[DataProvider('chargeProvider')]
    public function test_calculates_the_charge_for_each_commission_type(
        CommissionType $type,
        string $appliedValue,
        string $baseAmount,
        string $expected,
    ): void {
        $calculator = new PlatformChargeCalculator;

        $this->assertSame($expected, $calculator->calculate($type, $appliedValue, $baseAmount));
    }
}
