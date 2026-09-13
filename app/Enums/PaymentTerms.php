<?php

declare(strict_types=1);

namespace App\Enums;

/** Condiciones de pago acordadas con un proveedor o un hotel. */
enum PaymentTerms: string
{
    case Advance30 = 'advance_30';
    case Advance50 = 'advance_50';
    case Prepaid = 'prepaid';
    case Credit15 = 'credit_15';
    case Credit30 = 'credit_30';

    public function label(): string
    {
        return match ($this) {
            self::Advance30 => __('30% advance'),
            self::Advance50 => __('50% advance, balance on completion'),
            self::Prepaid => __('Paid in full up front'),
            self::Credit15 => __('15 day credit'),
            self::Credit30 => __('30 day credit'),
        };
    }
}
