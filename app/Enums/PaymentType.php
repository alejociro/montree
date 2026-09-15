<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentType: string
{
    case Full = 'full';
    case Partial = 'partial';
    case Remainder = 'remainder';

    /** Ver el WHY de [[PaymentStatus::label()]]: el idioma de origen es el español. */
    public function label(): string
    {
        return match ($this) {
            self::Full => __('Pago total'),
            self::Partial => __('Abono'),
            self::Remainder => __('Pago del saldo'),
        };
    }
}
