<?php

declare(strict_types=1);

namespace App\Services\SuperAdmin;

use App\Concerns\BelongsToTenant;
use App\Data\SuperAdmin\PlatformMetrics;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PlatformCharge;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MonthlySeries;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Las métricas del panel de plataforma. Todas las consultas cruzan tenants, así
 * que las tablas con `BelongsToTenant` se leen sin su global scope.
 */
final class PlatformMetricsAggregator
{
    private const CHART_MONTHS = 12;

    private const REVENUE_CHART_MONTHS = 6;

    private const REVENUE_CHART_TENANTS = 8;

    public function collect(CarbonInterface $from, CarbonInterface $to): PlatformMetrics
    {
        $previousFrom = CarbonImmutable::instance($from)->subMonthNoOverflow()->startOfMonth();
        $previousTo = CarbonImmutable::instance($from)->subMonthNoOverflow()->endOfMonth();

        $bookingsThisMonth = $this->bookingsBetween($from, $to);
        $bookingsPreviousMonth = $this->bookingsBetween($previousFrom, $previousTo);

        return new PlatformMetrics(
            totalTenants: Tenant::query()->count(),
            activeTenants: Tenant::query()->where('status', TenantStatus::Active->value)->count(),
            totalUsers: User::query()->count(),
            bookingsThisMonth: $bookingsThisMonth,
            revenueThisMonth: $this->decimal(
                Payment::query()
                    ->withoutGlobalScope(BelongsToTenant::class)
                    ->completed()
                    ->whereBetween('processed_at', [$from, $to])
                    ->sum('amount'),
            ),
            earningsThisMonth: PlatformCharge::query()->chargedBetween($from, $to)->totalAmount(),
            tenantsNewThisMonth: Tenant::query()->whereBetween('created_at', [$from, $to])->count(),
            bookingsGrowthPct: $this->growthPercentage($bookingsPreviousMonth, $bookingsThisMonth),
            planDistribution: $this->planDistribution(),
            charts: $this->charts($to),
        );
    }

    /**
     * Reservas, ingresos y cargos de los últimos 30 días para VARIOS tenants a la
     * vez: el listado paginado pide 15 filas y tres consultas agrupadas cuestan lo
     * mismo que una.
     *
     * @param  list<int>  $tenantIds
     * @return array<int, array{bookings_count_30d: int, revenue_30d: string, charges_30d: string}>
     */
    public function statsForTenants(array $tenantIds): array
    {
        if ($tenantIds === []) {
            return [];
        }

        $since = CarbonImmutable::now()->subDays(30);

        $bookings = Booking::query()
            ->withoutGlobalScope(BelongsToTenant::class)
            ->whereIn('tenant_id', $tenantIds)
            ->where('created_at', '>=', $since)
            ->whereIn('status', [
                BookingStatus::Confirmed->value,
                BookingStatus::Completed->value,
                BookingStatus::PendingPayment->value,
            ])
            ->selectRaw('tenant_id, COUNT(*) as aggregate')
            ->groupBy('tenant_id')
            ->pluck('aggregate', 'tenant_id');

        $revenue = Payment::query()
            ->withoutGlobalScope(BelongsToTenant::class)
            ->whereIn('tenant_id', $tenantIds)
            ->where('status', PaymentStatus::Completed->value)
            ->where('processed_at', '>=', $since)
            ->selectRaw('tenant_id, SUM(amount) as aggregate')
            ->groupBy('tenant_id')
            ->pluck('aggregate', 'tenant_id');

        $charges = PlatformCharge::query()
            ->whereIn('tenant_id', $tenantIds)
            ->where('charged_at', '>=', $since)
            ->selectRaw('tenant_id, SUM(amount) as aggregate')
            ->groupBy('tenant_id')
            ->pluck('aggregate', 'tenant_id');

        $stats = [];

        foreach ($tenantIds as $tenantId) {
            $stats[$tenantId] = [
                'bookings_count_30d' => (int) ($bookings[$tenantId] ?? 0),
                'revenue_30d' => $this->decimal($revenue[$tenantId] ?? 0),
                'charges_30d' => $this->decimal($charges[$tenantId] ?? 0),
            ];
        }

        return $stats;
    }

    /**
     * @return array{bookings: list<array{month: string, label: string, value: int|string}>, charges: list<array{month: string, label: string, value: int|string}>}
     */
    public function monthlyForTenant(Tenant $tenant): array
    {
        [$from, $to] = $this->window(self::CHART_MONTHS);
        $months = MonthlySeries::months($from, $to);

        $bookingDates = Booking::query()
            ->withoutGlobalScope(BelongsToTenant::class)
            ->where('tenant_id', $tenant->id)
            ->whereBetween('created_at', [$from, $to])
            ->get(['created_at'])
            ->map(fn (Booking $booking) => $booking->created_at);

        return [
            'bookings' => MonthlySeries::points($months, MonthlySeries::count($bookingDates)),
            'charges' => MonthlySeries::points(
                $months,
                PlatformCharge::query()->forTenant($tenant->id)->monthlyTotals($from, $to),
                '0.00',
            ),
        ];
    }

    /**
     * @return array{total_amount: string, total_count: int, currency: string}
     */
    public function chargesSummaryForTenant(Tenant $tenant): array
    {
        $charges = PlatformCharge::query()->forTenant($tenant->id);

        $tenant->loadMissing('configuration');

        return [
            'total_amount' => $charges->clone()->totalAmount(),
            'total_count' => $charges->clone()->count(),
            'currency' => $tenant->configuration?->currency ?? 'USD',
        ];
    }

    /**
     * @return array{
     *     tenants_per_month: array{points: list<array{month: string, label: string, value: int|string}>, average: float},
     *     revenue_per_tenant: array{months: list<string>, series: list<array{tenant: string, values: list<string>}>},
     *     earnings_per_month: array{points: list<array{month: string, label: string, value: int|string}>, total: string}
     * }
     */
    private function charts(CarbonInterface $to): array
    {
        [$from, $end] = $this->window(self::CHART_MONTHS, $to);
        $months = MonthlySeries::months($from, $end);

        $registered = MonthlySeries::points($months, Tenant::query()->registeredPerMonth($from, $end));
        $earnings = PlatformCharge::query()->monthlyTotals($from, $end);

        return [
            'tenants_per_month' => [
                'points' => $registered,
                'average' => round(array_sum(array_column($registered, 'value')) / max(count($months), 1), 1),
            ],
            'revenue_per_tenant' => $this->revenuePerTenant($end),
            'earnings_per_month' => [
                'points' => MonthlySeries::points($months, $earnings, '0.00'),
                'total' => array_reduce(
                    array_values($earnings),
                    static fn (string $carry, string $amount): string => bcadd($carry, $amount, 2),
                    '0.00',
                ),
            ],
        ];
    }

    /**
     * @return array{months: list<string>, series: list<array{tenant: string, values: list<string>}>}
     */
    private function revenuePerTenant(CarbonInterface $to): array
    {
        [$from, $end] = $this->window(self::REVENUE_CHART_MONTHS, $to);
        $months = MonthlySeries::months($from, $end);

        $byTenant = Payment::query()
            ->withoutGlobalScope(BelongsToTenant::class)
            ->monthlyRevenueByTenant($from, $end);

        $names = Tenant::query()
            ->whereIn('id', array_keys($byTenant))
            ->pluck('name', 'id');

        $totals = array_map(
            static fn (array $series): float => array_sum(array_map('floatval', $series)),
            $byTenant,
        );
        arsort($totals);

        $series = [];

        foreach (array_slice(array_keys($totals), 0, self::REVENUE_CHART_TENANTS) as $tenantId) {
            $series[] = [
                'tenant' => (string) ($names[$tenantId] ?? $tenantId),
                'values' => array_map(
                    static fn (string $month): string => $byTenant[$tenantId][$month] ?? '0.00',
                    $months,
                ),
            ];
        }

        return [
            'months' => array_map(MonthlySeries::label(...), $months),
            'series' => $series,
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function window(int $months, ?CarbonInterface $to = null): array
    {
        $end = CarbonImmutable::instance($to ?? CarbonImmutable::now())->endOfMonth();

        return [$end->subMonthsNoOverflow($months - 1)->startOfMonth(), $end];
    }

    private function bookingsBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        return Booking::query()
            ->withoutGlobalScope(BelongsToTenant::class)
            ->whereBetween('created_at', [$from, $to])
            ->count();
    }

    private function growthPercentage(int $previous, int $current): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * @return array<string, int>
     */
    private function planDistribution(): array
    {
        $counts = Tenant::query()
            ->selectRaw('plan, COUNT(*) as total')
            ->groupBy('plan')
            ->pluck('total', 'plan')
            ->all();

        $distribution = [];

        foreach (TenantPlan::cases() as $plan) {
            $distribution[$plan->value] = (int) ($counts[$plan->value] ?? 0);
        }

        return $distribution;
    }

    private function decimal(int|float|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
