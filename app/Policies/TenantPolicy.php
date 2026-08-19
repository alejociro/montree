<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

final class TenantPolicy
{
    public function view(User $user, Tenant $tenant): bool
    {
        return $user->can('tenant.view');
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $user->can('tenant.update');
    }

    public function updateSettings(User $user, Tenant $tenant): bool
    {
        return $user->can('tenant.settings.update');
    }
}
