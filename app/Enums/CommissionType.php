<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => __('Porcentaje por reserva'),
            self::Fixed => __('Monto fijo por reserva'),
        };
    }
}
