<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AgencyRegistered;
use App\Notifications\Onboarding\VerifyAgencyEmail;

final class SendAgencyVerificationEmail
{
    public function handle(AgencyRegistered $event): void
    {
        $event->founder->notify(VerifyAgencyEmail::for($event->tenant, $event->founder));
    }
}
