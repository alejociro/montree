<?php

declare(strict_types=1);

namespace App\Services\PlaceToPay;

use App\Models\Tenant;

/**
 * Comercio con el que se cobra: el propio del tenant si lo configuró, y el de
 * plataforma como respaldo.
 */
final readonly class CheckoutCredentials
{
    public function __construct(
        public string $login,
        public string $tranKey,
        public string $url,
    ) {}

    public static function resolve(Tenant $tenant): ?self
    {
        $configuration = $tenant->configuration;
        $hasOwnMerchant = filled($configuration?->placetopay_login);

        $login = $hasOwnMerchant ? $configuration->placetopay_login : config('placetopay.login');
        $tranKey = $hasOwnMerchant ? $configuration->placetopay_tran_key : config('placetopay.tran_key');

        /**
         * La URL viaja con el comercio: un login de Ecuador no autentica contra
         * el checkout de Colombia. Solo se hereda la de plataforma cuando el
         * tenant no eligió una.
         */
        $url = $hasOwnMerchant
            ? ($configuration->placetopay_url ?: config('placetopay.url'))
            : config('placetopay.url');

        if (blank($login) || blank($tranKey) || blank($url)) {
            return null;
        }

        return new self((string) $login, (string) $tranKey, (string) $url);
    }
}
