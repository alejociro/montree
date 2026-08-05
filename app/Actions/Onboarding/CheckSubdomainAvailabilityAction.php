<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Data\SubdomainAvailability;
use App\Enums\SubdomainAvailabilityReason;
use App\Models\Tenant;
use App\Multitenancy\SubdomainTenantFinder;

final class CheckSubdomainAvailabilityAction
{
    private const SLUG_PATTERN = '/^[a-z0-9][a-z0-9-]{1,62}$/';

    public function handle(string $slug): SubdomainAvailability
    {
        $slug = mb_strtolower($slug);

        $reason = $this->resolveReason($slug);

        return new SubdomainAvailability($slug, $reason === null, $reason);
    }

    private function resolveReason(string $slug): ?SubdomainAvailabilityReason
    {
        if (! preg_match(self::SLUG_PATTERN, $slug)) {
            return SubdomainAvailabilityReason::InvalidFormat;
        }

        if (SubdomainTenantFinder::isReservedSlug($slug)) {
            return SubdomainAvailabilityReason::Reserved;
        }

        if (Tenant::query()->where('slug', $slug)->exists()) {
            return SubdomainAvailabilityReason::Taken;
        }

        return null;
    }
}
