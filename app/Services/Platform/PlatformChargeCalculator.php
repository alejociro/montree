<?php

declare(strict_types=1);

namespace App\Services\Platform;

/**
 * Cuánto cobra la plataforma por una reserva. Puro: sin base de datos y sin
 * fechas, para que el redondeo se pueda probar sin montar un tenant.
 */
final class PlatformChargeCalculator
{
    /**
     * Busca el rango en el que cae `$baseAmount` (from inclusivo, to
     * exclusivo), cobra `base × rate / 100` sobre TODO el valor de la
     * reserva (no escalonado) y aplica `$maxCharge` -si viene- como tope del
     * ESQUEMA: `min(base × rate / 100, maxCharge)` sin importar el rango.
     * `null` si ningún rango cubre el monto (esquema mal formado; no debería
     * pasar si pasó por `ValidCommissionTiers`).
     *
     * @param  array<int, array{from: string, to: string|null, rate: string}>  $tiers
     * @return array{amount: string, rate: string, tier_from: string, tier_to: string|null, was_capped: bool}|null
     */
    public function calculateForTiers(array $tiers, string $baseAmount, ?string $maxCharge = null): ?array
    {
        foreach ($tiers as $tier) {
            $from = (string) $tier['from'];
            $to = $tier['to'] === null ? null : (string) $tier['to'];
            $rate = (string) $tier['rate'];

            $withinLowerBound = bccomp($baseAmount, $from, 6) >= 0;
            $withinUpperBound = $to === null || bccomp($baseAmount, $to, 6) < 0;

            if (! $withinLowerBound || ! $withinUpperBound) {
                continue;
            }

            $raw = bcdiv(bcmul($baseAmount, $rate, 6), '100', 6);
            $wasCapped = $maxCharge !== null && bccomp($raw, $maxCharge, 6) > 0;

            return [
                'amount' => $this->round($wasCapped ? $maxCharge : $raw),
                'rate' => $rate,
                'tier_from' => $from,
                'tier_to' => $to,
                'was_capped' => $wasCapped,
            ];
        }

        return null;
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
