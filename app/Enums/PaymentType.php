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
            self::Full => 'Full payment',
            self::Partial => 'Partial payment',
            self::Remainder => 'Remainder payment',
        };
    }
}
