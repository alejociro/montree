<?php

declare(strict_types=1);

namespace App\Enums;

enum TourStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Active => __('Active'),
            self::Paused => __('Paused'),
            self::Archived => __('Archived'),
        };
    }
}
