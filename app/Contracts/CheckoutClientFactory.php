<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Exceptions\PaymentException;
use App\Models\Tenant;
use Dnetix\Redirection\PlacetoPay;

interface CheckoutClientFactory
{
    /**
     * @throws PaymentException cuando el tenant no tiene comercio propio ni existe el de plataforma
     */
    public function for(Tenant $tenant): PlacetoPay;
}
