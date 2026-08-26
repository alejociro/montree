<?php

declare(strict_types=1);

namespace App\Enums;

/** Servicios que el hotel incluye sin costo extra para el viajero. */
enum HotelAmenity: string
{
    case Breakfast = 'breakfast';
    case Lunch = 'lunch';
    case Dinner = 'dinner';
    case Wifi = 'wifi';
    case HotWater = 'hot_water';
    case Parking = 'parking';
    case Pool = 'pool';
    case Bonfire = 'bonfire';
    case Laundry = 'laundry';
    case WheelchairAccess = 'wheelchair_access';

    public function label(): string
    {
        return match ($this) {
            self::Breakfast => __('Breakfast'),
            self::Lunch => __('Lunch'),
            self::Dinner => __('Dinner'),
            self::Wifi => __('Wi-Fi'),
            self::HotWater => __('Hot water'),
            self::Parking => __('Parking'),
            self::Pool => __('Pool'),
            self::Bonfire => __('Bonfire'),
            self::Laundry => __('Laundry'),
            self::WheelchairAccess => __('Wheelchair access'),
        };
    }
}
