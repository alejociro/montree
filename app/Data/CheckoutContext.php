<?php

declare(strict_types=1);

namespace App\Data;

/**
 * Datos del navegador que PlacetoPay exige en la creación de la sesión
 * (`ipAddress` y `userAgent` son obligatorios) más el idioma del checkout.
 *
 * WHY: existe para que la Action no reciba el Request. Los arma el controller,
 * que es el único que conoce HTTP.
 */
final readonly class CheckoutContext
{
    public function __construct(
        public string $ipAddress,
        public string $userAgent,
        public string $locale,
    ) {}
}
