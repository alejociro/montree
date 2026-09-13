<?php

declare(strict_types=1);

namespace App\Enums;

/** Ventanas del año en las que la ruta se opera bien. */
enum RouteSeason: string
{
    case AllYear = 'all_year';
    case DecemberFebruary = 'december_february';
    case JuneAugust = 'june_august';
    case AvoidRain = 'avoid_rain';

    public function label(): string
    {
        return match ($this) {
            self::AllYear => __('All year'),
            self::DecemberFebruary => __('December to February'),
            self::JuneAugust => __('June to August'),
            self::AvoidRain => __('Avoid the rainy season'),
        };
    }
}
