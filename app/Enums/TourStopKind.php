<?php

declare(strict_types=1);

namespace App\Enums;

enum TourStopKind: string
{
    case Pickup = 'pickup';
    case Site = 'site';
    case Drop = 'drop';

    public function label(): string
    {
        return match ($this) {
            self::Pickup => __('Pickup'),
            self::Site => __('Tour stop'),
            self::Drop => __('Drop-off'),
        };
    }
}
