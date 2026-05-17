<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\TenantConfiguration;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TenantConfigurationUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(public TenantConfiguration $configuration) {}
}
