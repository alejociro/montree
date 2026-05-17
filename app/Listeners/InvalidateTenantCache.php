<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TenantConfigurationUpdated;
use App\Events\TenantUpdated;
use App\Services\Tenant\TenantConfigurationCache;

final class InvalidateTenantCache
{
    public function __construct(private TenantConfigurationCache $cache) {}

    public function handle(TenantUpdated|TenantConfigurationUpdated $event): void
    {
        $tenant = $event instanceof TenantUpdated
            ? $event->tenant
            : $event->configuration->tenant;

        if ($tenant === null) {
            return;
        }

        $this->cache->forget($tenant->slug);
    }
}
