<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentType: string
{
    case Full = 'full';
    case Partial = 'partial';
    case Remainder = 'remainder';

    public function label(): string
    {
        return match ($this) {
            self::Full => __('Full payment'),
            self::Partial => __('Partial payment'),
            self::Remainder => __('Remainder payment'),
        };
    }
}
