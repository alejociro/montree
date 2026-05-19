<?php

declare(strict_types=1);

namespace App\Services\Tour;

use App\Models\Tenant;
use App\Models\Tour;

final class PlanLimitChecker
{
    public function canCreateTour(Tenant $tenant): bool
    {
        return $this->remainingTours($tenant) > 0;
    }

    public function maxToursForTenant(Tenant $tenant): int
    {
        $override = $tenant->plan_limits['max_tours'] ?? null;

        if (is_int($override)) {
            return $override;
        }

        $limit = $tenant->plan->limits()['max_tours'] ?? 0;

        return is_int($limit) ? $limit : 0;
    }

    private function remainingTours(Tenant $tenant): int
    {
        $max = $this->maxToursForTenant($tenant);
        $current = Tour::query()->count();

        return $max - $current;
    }
}
