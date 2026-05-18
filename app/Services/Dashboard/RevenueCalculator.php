<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Data\Dashboard\RevenueBreakdown;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

final class RevenueCalculator
{
    public function between(Tenant $tenant, Carbon $start, Carbon $end, Carbon $previousStart, Carbon $previousEnd): RevenueBreakdown
    {
        $currency = $tenant->configuration?->currency ?? 'USD';

        $gross = $this->sumGross($start, $end);
        $previousGross = $this->sumGross($previousStart, $previousEnd);
        $refunds = $this->sumRefunds($start, $end);

        $net = bcsub($gross, $refunds, 2);

        return new RevenueBreakdown(
            gross: $gross,
            net: $net,
            previousGross: $previousGross,
            growthPct: $this->growthPct($gross, $previousGross),
            currency: $currency,
        );
    }

    private function sumGross(Carbon $start, Carbon $end): string
    {
        $value = Payment::query()
            ->where('status', PaymentStatus::Completed->value)
            ->whereBetween('processed_at', [$start, $end])
            ->sum('amount');

        return number_format((float) $value, 2, '.', '');
    }

    private function sumRefunds(Carbon $start, Carbon $end): string
    {
        $value = Payment::query()
            ->whereIn('status', [PaymentStatus::Refunded->value, PaymentStatus::PartiallyRefunded->value])
            ->whereBetween('refunded_at', [$start, $end])
            ->sum('refunded_amount');

        return number_format((float) $value, 2, '.', '');
    }

    private function growthPct(string $current, string $previous): ?float
    {
        if (bccomp($previous, '0.00', 2) === 0) {
            return null;
        }

        $diff = bcsub($current, $previous, 4);
        $ratio = bcdiv($diff, $previous, 4);

        return round((float) $ratio * 100, 1);
    }
}
