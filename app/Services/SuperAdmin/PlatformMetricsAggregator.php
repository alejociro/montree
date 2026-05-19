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
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;

final class PlatformMetricsAggregator
{
    private const PLATFORM_COMMISSION_RATE = 0.03;

    public function collect(?Carbon $from = null, ?Carbon $to = null): PlatformMetrics
    {
        $from ??= Carbon::now()->startOfMonth();
        $to ??= Carbon::now()->endOfMonth();

        $previousFrom = $from->copy()->subMonthNoOverflow()->startOfMonth();
        $previousTo = $from->copy()->subMonthNoOverflow()->endOfMonth();

        $totalTenants = Tenant::query()->count();
        $activeTenants = Tenant::query()->where('status', TenantStatus::Active->value)->count();
        $totalUsers = User::query()->count();

        // WHY: super admin aggregates across all tenants; bypass the BelongsToTenant scope.
        $bookingsThisMonth = Booking::query()
            ->withoutGlobalScope(BelongsToTenant::class)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $bookingsPreviousMonth = Booking::query()
            ->withoutGlobalScope(BelongsToTenant::class)
            ->whereBetween('created_at', [$previousFrom, $previousTo])
            ->count();

        // WHY: cross-tenant payment revenue aggregation skips tenant scope on purpose.
        $revenueThisMonth = (string) Payment::query()
            ->withoutGlobalScope(BelongsToTenant::class)
            ->where('status', PaymentStatus::Completed->value)
            ->whereBetween('processed_at', [$from, $to])
            ->sum('amount');

        $platformCommission = number_format(
            (float) $revenueThisMonth * self::PLATFORM_COMMISSION_RATE,
            2,
            '.',
            '',
        );

        $tenantsNewThisMonth = Tenant::query()
            ->whereBetween('created_at', [$from, $to])
            ->count();

        return new PlatformMetrics(
            totalTenants: $totalTenants,
            activeTenants: $activeTenants,
            totalUsers: $totalUsers,
            bookingsThisMonth: $bookingsThisMonth,
            revenueThisMonth: number_format((float) $revenueThisMonth, 2, '.', ''),
            platformCommissionThisMonth: $platformCommission,
            tenantsNewThisMonth: $tenantsNewThisMonth,
            bookingsGrowthPct: $this->growthPercentage($bookingsPreviousMonth, $bookingsThisMonth),
            planDistribution: $this->planDistribution(),
        );
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

    /**
     * @return array{users_count: int, tours_count: int, bookings_count_30d: int, revenue_30d: string}
     */
    public function statsForTenant(Tenant $tenant): array
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        $usersCount = $tenant->users()->count();
        $toursCount = $tenant->tours()->count();

        $bookings30d = Booking::query()
            ->withoutGlobalScope(BelongsToTenant::class)
            ->where('tenant_id', $tenant->id)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->whereIn('status', [
                BookingStatus::Confirmed->value,
                BookingStatus::Completed->value,
                BookingStatus::PendingPayment->value,
            ])
            ->count();

        $revenue30d = (string) Payment::query()
            ->withoutGlobalScope(BelongsToTenant::class)
            ->where('tenant_id', $tenant->id)
            ->where('status', PaymentStatus::Completed->value)
            ->where('processed_at', '>=', $thirtyDaysAgo)
            ->sum('amount');

        return [
            'users_count' => $usersCount,
            'tours_count' => $toursCount,
            'bookings_count_30d' => $bookings30d,
            'revenue_30d' => number_format((float) $revenue30d, 2, '.', ''),
        ];
    }
}
