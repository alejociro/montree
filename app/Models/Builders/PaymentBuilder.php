<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\MonthlySeries;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<Payment>
 */
final class PaymentBuilder extends Builder
{
    public function completed(): self
    {
        return $this->where('status', PaymentStatus::Completed);
    }

    /**
     * Ingresos cobrados, por tenant y por mes. La clave externa es el
     * `tenant_id`; la interna, el mes `YYYY-MM`.
     *
     * @return array<int, array<string, string>>
     */
    public function monthlyRevenueByTenant(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = $this->clone()
            ->completed()
            ->whereBetween('processed_at', [$from, $to])
            ->get(['tenant_id', 'processed_at', 'amount']);

        $byTenant = [];

        foreach ($rows->groupBy('tenant_id') as $tenantId => $payments) {
            $byTenant[(int) $tenantId] = MonthlySeries::sum(
                $payments->map(fn (Payment $payment): array => [$payment->processed_at, $payment->amount]),
            );
        }

        return $byTenant;
    }
}
