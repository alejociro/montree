<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Onboarding\OnboardingHandoff;
use Illuminate\Http\Request;

final class ActivateAgencyAction
{
    public function __construct(private OnboardingHandoff $handoff) {}

    /**
     * Verify the founder's email and activate their agency. Idempotent:
     * replaying the verification link on an already-active agency only
     * re-issues a fresh claim handoff.
     */
    public function handle(Tenant $tenant, User $user, Request $request): string
    {
        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        if ($tenant->status === TenantStatus::Pending) {
            $tenant->status = TenantStatus::Active;
            $tenant->save();
        }

        return $this->handoff->issueClaimUrl($tenant, $user, $request);
    }
}
