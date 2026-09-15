<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentGateway: string
{
    case PlaceToPay = 'placetopay';
    case Cash = 'cash';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::PlaceToPay => __('PlacetoPay'),
            self::Cash => __('Efectivo'),
            self::Transfer => __('Transferencia'),
        };
    }

    /**
     * WHY: la columna se llama `gateway` pero ya solo uno de los medios es una
     * pasarela. Sin esto cada consumidor repite la comparación por string.
     */
    public function isGateway(): bool
    {
        return $this === self::PlaceToPay;
    }
}
