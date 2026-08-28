<?php

declare(strict_types=1);

namespace App\Enums;

/** Tipo de propiedad donde duerme el grupo. */
enum AccommodationType: string
{
    case Ecolodge = 'ecolodge';
    case Hotel = 'hotel';
    case RuralInn = 'rural_inn';
    case Hostel = 'hostel';
    case Glamping = 'glamping';
    case Farm = 'farm';

    public function label(): string
    {
        return match ($this) {
            self::Ecolodge => __('Ecolodge'),
            self::Hotel => __('Hotel'),
            self::RuralInn => __('Rural inn'),
            self::Hostel => __('Hostel'),
            self::Glamping => __('Glamping'),
            self::Farm => __('Farm'),
        };
    }
}
