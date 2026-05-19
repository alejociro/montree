<?php

declare(strict_types=1);

namespace App\Http\Resources\SuperAdmin;

use App\Data\SuperAdmin\PlatformMetrics;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PlatformMetrics $resource
 */
class PlatformMetricsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $metrics = $this->resource;

        return [
            'totals' => [
                'tenants' => $metrics->totalTenants,
                'active_tenants' => $metrics->activeTenants,
                'users' => $metrics->totalUsers,
                'bookings_this_month' => $metrics->bookingsThisMonth,
                'revenue_this_month' => $metrics->revenueThisMonth,
                'platform_commission_this_month' => $metrics->platformCommissionThisMonth,
            ],
            'growth' => [
                'tenants_new_this_month' => $metrics->tenantsNewThisMonth,
                'bookings_growth_pct' => $metrics->bookingsGrowthPct,
            ],
            'plan_distribution' => $metrics->planDistribution,
        ];
    }
}
