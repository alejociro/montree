<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Enums\TenantMembershipStatus;
use App\Exceptions\CrossTenantAccessException;
use App\Models\Tenant;
use App\Models\User;

final class UpdateMemberStatusAction
{
    public function handle(Tenant $tenant, User $user, TenantMembershipStatus $status): User
    {
        if (! $user->belongsToTenant($tenant)) {
            throw CrossTenantAccessException::forMember();
        }

        $payload = ['status' => $status->value];
        if ($status === TenantMembershipStatus::Suspended) {
            $payload['suspended_at'] = now();
        }
        $tenant->users()->updateExistingPivot($user->id, $payload);

        return $user->fresh();
    }
}
