<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Onboarding\OnboardingHandoff;

final class ClaimAgencyAccessAction
{
    public function __construct(private OnboardingHandoff $handoff) {}

    /**
     * Consume the one-shot handoff nonce and resolve the founder to log in, only
     * if they are still an active member of the resolved subdomain tenant.
     */
    public function handle(string $nonce, ?Tenant $tenant): ?User
    {
        if ($tenant === null) {
            return null;
        }

        $payload = $this->handoff->consume($nonce);

        if ($payload === null || $payload['tenant_id'] !== $tenant->getKey()) {
            return null;
        }

        $user = User::find($payload['user_id']);

        if ($user === null || ! $user->isActiveMemberOf($tenant)) {
            return null;
        }

        return $user;
    }
}
