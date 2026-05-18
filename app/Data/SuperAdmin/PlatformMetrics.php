<?php

declare(strict_types=1);

namespace App\Data\SuperAdmin;

final readonly class PlatformMetrics
{
    /**
     * @param  array<string, int>  $planDistribution
     */
    public function __construct(
        public int $totalTenants,
        public int $activeTenants,
        public int $totalUsers,
        public int $bookingsThisMonth,
        public string $revenueThisMonth,
        public string $platformCommissionThisMonth,
        public int $tenantsNewThisMonth,
        public float $bookingsGrowthPct,
        public array $planDistribution,
    ) {}
}
