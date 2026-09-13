<?php

declare(strict_types=1);

namespace App\Services\PlaceToPay;

use App\Contracts\CheckoutClientFactory;
use App\Exceptions\PaymentException;
use App\Models\Tenant;
use Dnetix\Redirection\PlacetoPay;
use Illuminate\Support\Facades\Log;

/**
 * WHY: el cliente se construye por tenant y no se cachea en el contenedor. Un
 * mismo proceso (queue worker, `payment:check`) atiende varias agencias, y un
 * cliente compartido cobraría al comercio equivocado.
 */
final class TenantCheckoutClientFactory implements CheckoutClientFactory
{
    public function for(Tenant $tenant): PlacetoPay
    {
        $credentials = CheckoutCredentials::resolve($tenant);

        if ($credentials === null) {
            throw PaymentException::gatewayNotConfigured();
        }

        return new PlacetoPay([
            'login' => $credentials->login,
            'tranKey' => $credentials->tranKey,
            'baseUrl' => $credentials->url,
            'timeout' => (int) config('placetopay.timeout'),
            'logger' => Log::getLogger(),
        ]);
    }
}
