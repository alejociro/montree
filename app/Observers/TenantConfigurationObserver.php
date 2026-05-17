<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\TenantConfigurationUpdated;
use App\Models\TenantConfiguration;

final class TenantConfigurationObserver
{
    public function saved(TenantConfiguration $configuration): void
    {
        TenantConfigurationUpdated::dispatch($configuration);
    }

    public function deleted(TenantConfiguration $configuration): void
    {
        TenantConfigurationUpdated::dispatch($configuration);
    }
}
