<?php

declare(strict_types=1);

namespace App\Enums;

/** Unidad con la que se cobra una tarifa. */
enum RateUnit: string
{
    case PerDay = 'per_day';
    case PerPerson = 'per_person';
    case PerService = 'per_service';
    case PerHour = 'per_hour';

    public function label(): string
    {
        return match ($this) {
            self::PerDay => __('Per day'),
            self::PerPerson => __('Per person'),
            self::PerService => __('Per service'),
            self::PerHour => __('Per hour'),
        };
    }
}
