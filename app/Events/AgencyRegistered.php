<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class AgencyRegistered
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public User $founder,
    ) {}
}
