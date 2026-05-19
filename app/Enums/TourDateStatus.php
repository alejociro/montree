<?php

declare(strict_types=1);

namespace App\Enums;

enum TourDateStatus: string
{
    case Open = 'open';
    case Full = 'full';
    case Cancelled = 'cancelled';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Full => 'Full',
            self::Cancelled => 'Cancelled',
            self::Closed => 'Closed',
        };
    }
}
