<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Enums\TenantMembershipStatus;
use App\Models\Tenant;
use App\Models\User;

final class UpdateMemberStatusAction
{
    public function handle(Tenant $tenant, User $user, TenantMembershipStatus $status): User
    {
        $payload = ['status' => $status->value];
        if ($status === TenantMembershipStatus::Suspended) {
            $payload['suspended_at'] = now();
        }
        $tenant->users()->updateExistingPivot($user->id, $payload);

        return $user->fresh();
    }
}
