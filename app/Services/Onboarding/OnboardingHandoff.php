<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Single-use, short-lived handoff that logs an agency founder into their own
 * subdomain right after they verify their email on the platform host.
 *
 * WHY: sessions are isolated per subdomain (cookie host-only, see
 * docs/multi-tenancy.md §10), so the session created during verification on the
 * platform host does not travel to the tenant subdomain. The claim URL carries a
 * nonce (this cache entry) plus a Laravel signature; consuming the nonce is what
 * makes the link one-shot.
 */
final class OnboardingHandoff
{
    private const PREFIX = 'onboarding-claim:';

    private const TTL_SECONDS = 900;

    /**
     * Mint a nonce and build the absolute signed `claim` URL on the tenant
     * subdomain, preserving the request scheme and port (dev runs on `:8000`).
     */
    public function issueClaimUrl(Tenant $tenant, User $user, Request $request): string
    {
        $nonce = Str::random(64);

        Cache::put(self::PREFIX.$nonce, [
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
        ], self::TTL_SECONDS);

        return $this->signedClaimUrl($tenant, $request, $nonce);
    }

    /**
     * Consume a nonce. Single use: a valid nonce is deleted before returning so it
     * cannot be replayed.
     *
     * @return array{tenant_id: int, user_id: int}|null
     */
    public function consume(string $nonce): ?array
    {
        $key = self::PREFIX.$nonce;

        /** @var array{tenant_id: int, user_id: int}|null $payload */
        $payload = Cache::get($key);

        if ($payload === null) {
            return null;
        }

        Cache::forget($key);

        return $payload;
    }

    private function signedClaimUrl(Tenant $tenant, Request $request, string $nonce): string
    {
        $root = $this->subdomainRoot($tenant, $request);
        URL::forceRootUrl($root);

        try {
            return URL::temporarySignedRoute(
                'onboarding.claim',
                now()->addSeconds(self::TTL_SECONDS),
                ['nonce' => $nonce],
            );
        } finally {
            URL::forceRootUrl(null);
        }
    }

    private function subdomainRoot(Tenant $tenant, Request $request): string
    {
        $base = (string) preg_replace('/^www\./', '', mb_strtolower($request->getHost()));
        $host = $tenant->slug.'.'.$base;
        $port = $request->getPort();
        $portSuffix = in_array($port, [80, 443, null], true) ? '' : ':'.$port;

        return "{$request->getScheme()}://{$host}{$portSuffix}";
    }
}
