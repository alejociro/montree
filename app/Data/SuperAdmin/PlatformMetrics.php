<?php

declare(strict_types=1);

namespace App\Data\SuperAdmin;

final readonly class PlatformMetrics
{
    /**
     * @param  array<string, int>  $planDistribution
     * @param  array{
     *     tenants_per_month: array{points: list<array{month: string, label: string, value: int|string}>, average: float},
     *     revenue_per_tenant: array{months: list<string>, series: list<array{tenant: string, values: list<string>}>},
     *     earnings_per_month: array{points: list<array{month: string, label: string, value: int|string}>, total: string}
     * }  $charts
     */
    public function __construct(
        public int $totalTenants,
        public int $activeTenants,
        public int $totalUsers,
        public int $bookingsThisMonth,
        public string $revenueThisMonth,
        public string $earningsThisMonth,
        public int $tenantsNewThisMonth,
        public float $bookingsGrowthPct,
        public array $planDistribution,
        public array $charts,
    ) {}
}
