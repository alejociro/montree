<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\TenantUpdated;
use App\Models\Tenant;
use App\Services\Tenant\TenantConfigurationCache;

final class TenantObserver
{
    public function __construct(private TenantConfigurationCache $cache) {}

    public function updated(Tenant $tenant): void
    {
        TenantUpdated::dispatch($tenant);
    }

    public function deleted(Tenant $tenant): void
    {
        $this->cache->forget($tenant->slug);
    }
}
