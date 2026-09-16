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

    public function totalAmount(): string
    {
        return number_format((float) $this->clone()->sum('amount'), 2, '.', '');
    }
}
