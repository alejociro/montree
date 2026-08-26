<?php

declare(strict_types=1);

namespace App\Enums;

/** Qué presta un proveedor. */
enum ProviderServiceType: string
{
    case Transport = 'transport';
    case Food = 'food';
    case Guiding = 'guiding';
    case Activities = 'activities';
    case Equipment = 'equipment';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Transport => __('Transport'),
            self::Food => __('Food'),
            self::Guiding => __('Guiding'),
            self::Activities => __('Activities'),
            self::Equipment => __('Equipment'),
            self::Other => __('Other'),
        };
    }
}
