<?php

declare(strict_types=1);

namespace App\Services\PlaceToPay;

use App\Models\Tenant;

/**
 * La notificación viene firmada con `hash(requestId + status + date + tranKey)`.
 * Es la única credencial del endpoint: sin ella cualquiera podría hacernos
 * consultar —o peor, dar por bueno— un pago ajeno.
 */
final class NotificationSignature
{
    private const ALGORITHMS = ['sha1', 'sha256', 'sha512'];

    /**
     * @param  array{requestId: string, status: string, date: string, signature: string}  $notification
     */
    public function matches(Tenant $tenant, array $notification): bool
    {
        $credentials = CheckoutCredentials::resolve($tenant);

        if ($credentials === null) {
            return false;
        }

        [$algorithm, $signature] = $this->split($notification['signature']);

        if (! in_array($algorithm, self::ALGORITHMS, true)) {
            return false;
        }

        $expected = hash($algorithm, sprintf(
            '%s%s%s%s',
            $notification['requestId'],
            $notification['status'],
            $notification['date'],
            $credentials->tranKey,
        ));

        return hash_equals($expected, $signature);
    }

    /**
     * PlacetoPay manda `sha256:abc...` cuando no usa el sha1 histórico, que viaja pelado.
     *
     * @return array{0: string, 1: string}
     */
    private function split(string $signature): array
    {
        if (! str_contains($signature, ':')) {
            return ['sha1', $signature];
        }

        [$algorithm, $hash] = explode(':', $signature, 2);

        return [strtolower($algorithm), $hash];
    }
}
