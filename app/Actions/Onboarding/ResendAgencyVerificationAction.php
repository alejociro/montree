<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Onboarding\VerifyAgencyEmail;

final class ResendAgencyVerificationAction
{
    /**
     * Re-send the founder verification email for a still-pending agency. Silent
     * on every miss so the endpoint never reveals whether an account exists.
     */
    public function handle(string $email): void
    {
        $tenant = Tenant::query()
            ->where('contact_email', $email)
            ->where('status', TenantStatus::Pending->value)
            ->first();

        if ($tenant === null) {
            return;
        }

        $founder = User::query()->where('email', $email)->first();

        if ($founder === null) {
            return;
        }

        $founder->notify(VerifyAgencyEmail::for($tenant, $founder));
    }
}
