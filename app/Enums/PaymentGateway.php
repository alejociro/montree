<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentGateway: string
{
    case Stripe = 'stripe';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Stripe => 'Stripe',
            self::Manual => 'Manual',
        };
    }
}
