<?php

declare(strict_types=1);

namespace App\Enums;

enum PromotionType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => __('Percentage'),
            self::Fixed => __('Fixed amount'),
        };
    }
}
