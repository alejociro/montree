<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentGateway: string
{
    case PlaceToPay = 'placetopay';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::PlaceToPay => __('PlacetoPay'),
            self::Manual => __('Manual'),
        };
    }
}
