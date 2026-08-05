<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Onboarding\OnboardingHandoff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

final class ActivateAgencyAction
{
    public function __construct(private OnboardingHandoff $handoff) {}

    /**
     * Verify the founder's email and activate their agency, starting the trial.
     * Idempotent: replaying the verification link on an already-active agency only
     * re-issues a fresh claim handoff.
     */
    public function handle(Tenant $tenant, User $user, Request $request): string
    {
        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        if ($tenant->status === TenantStatus::Pending) {
            $tenant->status = TenantStatus::Active;
            $tenant->plan = $this->defaultPlan();
            $tenant->trial_ends_at = now()->addDays($this->trialDays());
            $tenant->save();
        }

        return $this->handoff->issueClaimUrl($tenant, $user, $request);
    }

    private function trialDays(): int
    {
        return (int) Config::get('montree.onboarding.trial_days', 14);
    }

    private function defaultPlan(): TenantPlan
    {
        $plan = Config::get('montree.onboarding.default_plan');

        return $plan instanceof TenantPlan ? $plan : TenantPlan::Professional;
    }
}
