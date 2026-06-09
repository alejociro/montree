<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Single-use, short-lived token that authorizes logging a user in on a DIFFERENT
 * host than the one where they authenticated.
 *
 * WHY: sessions are isolated per subdomain (cookie host-only, see
 * docs/multi-tenancy.md §10), so a session created on `montree.test` does not
 * travel to `admin.montree.test`. After a successful password login we hand the
 * user off across hosts with this token instead of relying on a shared cookie.
 */
final class CrossHostLoginHandoff
{
    private const PREFIX = 'auth-handoff:';

    private const TTL_SECONDS = 60;

    public function issue(User $user, string $redirectTo): string
    {
        $token = Str::random(64);

        Cache::put(self::PREFIX.$token, [
            'user_id' => $user->getKey(),
            'redirect_to' => $redirectTo,
        ], self::TTL_SECONDS);

        return $token;
    }

    /**
     * Consume a token. Single use: a valid token is deleted before returning so it
     * cannot be replayed.
     *
     * @return array{user_id: int, redirect_to: string}|null
     */
    public function consume(string $token): ?array
    {
        $key = self::PREFIX.$token;

        /** @var array{user_id: int, redirect_to: string}|null $payload */
        $payload = Cache::get($key);

        if ($payload === null) {
            return null;
        }

        Cache::forget($key);

        return $payload;
    }
}
