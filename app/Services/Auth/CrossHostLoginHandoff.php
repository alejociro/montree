<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Data\HandoffPayload;
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

    /**
     * WHY: el handoff de login viaja en la misma respuesta y vive 60 segundos. Un
     * enlace que sale por correo necesita el tiempo que el dueño tarda en abrir su
     * bandeja: se le da el mismo hold de la reserva (30 min), ni un minuto más.
     */
    public const EMAIL_TTL_SECONDS = 1800;

    public function issue(User $user, string $redirectTo, bool $remember = false, ?int $ttlSeconds = null): string
    {
        $token = Str::random(64);

        $payload = new HandoffPayload((int) $user->getKey(), $redirectTo, $remember);

        Cache::put(self::PREFIX.$token, $payload->toArray(), $ttlSeconds ?? self::TTL_SECONDS);

        return $token;
    }

    /**
     * Consume a token. Single use: `pull` lee y borra en una sola operación, de
     * modo que dos peticiones simultáneas con el mismo token no puedan resolverlo
     * las dos (un `get` + `forget` sí deja esa ventana abierta).
     */
    public function consume(string $token): ?HandoffPayload
    {
        /** @var array{user_id: int, redirect_to: string, remember?: bool}|null $payload */
        $payload = Cache::pull(self::PREFIX.$token);

        if ($payload === null) {
            return null;
        }

        return HandoffPayload::fromArray($payload);
    }
}
