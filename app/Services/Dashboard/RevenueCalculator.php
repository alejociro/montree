<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Data\Dashboard\RevenueBreakdown;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
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
            series: $this->grossSeries($start, $end),
            byMethod: $this->byMethod($start, $end, $gross),
        );
    }

    /**
     * Siempre los tres medios y en el orden del enum: un medio ausente no se
     * distingue de uno sin movimientos.
     *
     * @return list<array{method: string, label: string, amount: string, share_pct: int}>
     */
    private function byMethod(Carbon $start, Carbon $end, string $gross): array
    {
        $totals = $this->collectedPayments($start, $end)
            ->selectRaw('gateway, SUM(amount) as total')
            ->groupBy('gateway')
            ->pluck('total', 'gateway');

        return array_map(function (PaymentGateway $method) use ($totals, $gross): array {
            $amount = number_format((float) ($totals[$method->value] ?? 0), 2, '.', '');

            return [
                'method' => $method->value,
                'label' => $method->label(),
                'amount' => $amount,
                'share_pct' => $this->sharePct($amount, $gross),
            ];
        }, PaymentGateway::cases());
    }

    private function sharePct(string $amount, string $gross): int
    {
        if (bccomp($gross, '0.00', 2) === 0) {
            return 0;
        }

        return (int) round((float) bcdiv($amount, $gross, 4) * 100);
    }

    /**
     * @return list<array{date: string, amount: string}>
     */
    public function grossSeries(Carbon $start, Carbon $end): array
    {
        $granularity = $this->granularityFor($start, $end);

        $dailyTotals = $this->collectedPayments($start, $end)
            ->selectRaw('DATE(processed_at) as day, SUM(amount) as gross')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $buckets = [];

        foreach ($dailyTotals as $total) {
            $bucket = $this->bucketDate(Carbon::parse((string) $total->day), $granularity);
            $amount = number_format((float) $total->gross, 2, '.', '');

            $buckets[$bucket] = bcadd($buckets[$bucket] ?? '0.00', $amount, 2);
        }

        return array_map(
            fn (string $date, string $amount): array => ['date' => $date, 'amount' => $amount],
            array_keys($buckets),
            array_values($buckets),
        );
    }

    private function sumGross(Carbon $start, Carbon $end): string
    {
        $value = $this->collectedPayments($start, $end)->sum('amount');

        return number_format((float) $value, 2, '.', '');
    }

    /**
     * WHY: un pago reembolsado se cobró primero, así que cuenta en el bruto y se
     * descuenta en `sumRefunds()`. Sin eso el neto lo restaría dos veces.
     *
     * @return Builder<Payment>
     */
    private function collectedPayments(Carbon $start, Carbon $end): Builder
    {
        return Payment::query()
            ->whereIn('status', [PaymentStatus::Completed->value, PaymentStatus::Refunded->value])
            ->whereBetween('processed_at', [$start, $end]);
    }

    /**
     * WHY: a year grouped by day would ship 365 points to a sparkline that renders
     * a few dozen pixels wide, so longer periods collapse into coarser buckets.
     */
    private function granularityFor(Carbon $start, Carbon $end): string
    {
        $days = (int) floor($start->diffInDays($end)) + 1;

        return match (true) {
            $days <= 31 => 'day',
            $days <= 180 => 'week',
            default => 'month',
        };
    }

    private function bucketDate(Carbon $day, string $granularity): string
    {
        return match ($granularity) {
            'week' => $day->startOfWeek()->toDateString(),
            'month' => $day->startOfMonth()->toDateString(),
            default => $day->toDateString(),
        };
    }

    private function sumRefunds(Carbon $start, Carbon $end): string
    {
        $value = Payment::query()
            ->where('status', PaymentStatus::Refunded->value)
            ->whereBetween('processed_at', [$start, $end])
            ->sum('amount');

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
