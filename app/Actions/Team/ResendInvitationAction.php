<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Enums\TenantMembershipStatus;
use App\Exceptions\CrossTenantAccessException;
use App\Exceptions\TeamException;
use App\Models\Tenant;
use App\Models\User;

final class ResendInvitationAction
{
    public function __construct(private SendTeamInvitationAction $sendInvitation) {}

    public function handle(Tenant $tenant, User $user): User
    {
        $membership = $user->membershipFor($tenant);

        if ($membership === null) {
            throw CrossTenantAccessException::forMember();
        }

        if ($membership->status !== TenantMembershipStatus::Invited) {
            throw TeamException::invitationAlreadyAccepted();
        }

        $membership->forceFill(['invited_at' => now()])->save();

        $this->sendInvitation->handle($user, $tenant);

        return $user;
    }
}
