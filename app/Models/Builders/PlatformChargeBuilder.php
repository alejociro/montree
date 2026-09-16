<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Models\PlatformCharge;
use App\Support\MonthlySeries;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<PlatformCharge>
 */
final class PlatformChargeBuilder extends Builder
{
    public function forTenant(int $tenantId): self
    {
        return $this->where('tenant_id', $tenantId);
    }

    public function chargedBetween(?CarbonInterface $from, ?CarbonInterface $to): self
    {
        return $this
            ->when($from !== null, fn (self $query) => $query->where('charged_at', '>=', $from))
            ->when($to !== null, fn (self $query) => $query->where('charged_at', '<=', $to));
    }

    /**
     * @return array<string, string>
     */
    public function monthlyTotals(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = $this->clone()
            ->chargedBetween($from, $to)
            ->get(['charged_at', 'amount'])
            ->map(fn (PlatformCharge $charge): array => [$charge->charged_at, $charge->amount]);

        return MonthlySeries::sum($rows);
    }

    /**
     * Totales por moneda. Los cargos se guardan en la moneda de cada agencia y
     * no se convierten, así que sumarlos entre monedas daría un número falso.
     *
     * @return array<string, string>
     */
    public function totalsByCurrency(): array
    {
        $totals = $this->clone()
            ->selectRaw('currency, SUM(amount) as aggregate')
            ->groupBy('currency')
            ->orderBy('currency')
            ->pluck('aggregate', 'currency')
            ->all();

        return array_map(
            static fn (int|float|string $amount): string => number_format((float) $amount, 2, '.', ''),
            $totals,
        );
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function monthlyTotalsByCurrency(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = $this->clone()
            ->chargedBetween($from, $to)
            ->get(['currency', 'charged_at', 'amount']);

        $byCurrency = [];

        foreach ($rows->groupBy('currency') as $currency => $charges) {
            $byCurrency[(string) $currency] = MonthlySeries::sum(
                $charges->map(fn (PlatformCharge $charge): array => [$charge->charged_at, $charge->amount]),
            );
        }

        ksort($byCurrency);

        return $byCurrency;
    }

    public function totalAmount(): string
    {
        return number_format((float) $this->clone()->sum('amount'), 2, '.', '');
    }
}
