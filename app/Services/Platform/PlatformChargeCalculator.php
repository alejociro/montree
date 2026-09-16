<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Enums\CommissionType;

/**
 * Cuánto cobra la plataforma por una reserva. Puro: sin base de datos y sin
 * fechas, para que el redondeo se pueda probar sin montar un tenant.
 */
final class PlatformChargeCalculator
{
    public function calculate(CommissionType $type, string $appliedValue, string $baseAmount): string
    {
        return match ($type) {
            CommissionType::Percentage => $this->round(
                bcdiv(bcmul($baseAmount, $appliedValue, 6), '100', 6),
            ),
            CommissionType::Fixed => $this->round($appliedValue),
        };
    }

    /**
     * WHY: `bcdiv` trunca. Sumar medio centavo antes de recortar a dos decimales
     * es el redondeo al alza que espera la contabilidad, y lo hace sin pasar por
     * float (donde 0.145 ya no es 0.145).
     */
    private function round(string $amount): string
    {
        return bcadd($amount, '0.005', 2);
    }
}
