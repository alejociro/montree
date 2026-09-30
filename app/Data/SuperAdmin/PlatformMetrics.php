<?php

declare(strict_types=1);

namespace App\Data\SuperAdmin;

final readonly class PlatformMetrics
{
    /**
     * @param  list<array{currency: string, amount: string}>  $revenueThisMonth
     * @param  list<array{currency: string, amount: string}>  $earningsThisMonth
     * @param  array{
     *     tenants_per_month: array{points: list<array{month: string, label: string, value: int|string}>, average: float},
     *     revenue_per_tenant: array{months: list<string>, series: list<array{tenant: string, currency: string, values: list<string>}>},
     *     earnings_per_month: array{series: list<array{currency: string, points: list<array{month: string, label: string, value: int|string}>, total: string}>}
     * }  $charts
     */
    public function __construct(
        public int $totalTenants,
        public int $activeTenants,
        public int $totalUsers,
        public int $bookingsThisMonth,
        public array $revenueThisMonth,
        public array $earningsThisMonth,
        public int $tenantsNewThisMonth,
        public float $bookingsGrowthPct,
        public array $charts,
    ) {}
}
