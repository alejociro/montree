<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Valida destinos de redirección que llegan del cliente (`return`, `from`).
 *
 * WHY: solo se acepta una ruta interna. Un valor externo convertiría el
 * guardado en una redirección abierta; la barra invertida se rechaza porque
 * algunos navegadores normalizan `/\evil.com` a `//evil.com`.
 */
final class SafeRedirect
{
    public static function internalPath(mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || ! str_starts_with($value, '/')) {
            return null;
        }

        if (str_starts_with($value, '//') || str_contains($value, '\\') || str_contains($value, '://')) {
            return null;
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            return null;
        }

        return $value;
    }
}
