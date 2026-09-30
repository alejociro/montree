<?php

declare(strict_types=1);

namespace App\Services\PlaceToPay;

use App\Enums\PlaceToPayEnvironment;
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
         * La URL viaja con el ambiente del comercio: un comercio propio en
         * producción no autentica contra el checkout de pruebas. Solo se
         * hereda el ambiente de plataforma cuando el tenant usa el comercio
         * de plataforma.
         */
        $environment = $hasOwnMerchant
            ? $configuration->placetopay_environment
            : PlaceToPayEnvironment::from((string) config('placetopay.environment'));

        $url = $environment->url();

        if (blank($login) || blank($tranKey) || blank($url)) {
            return null;
        }

        return new self((string) $login, (string) $tranKey, (string) $url);
    }
}
