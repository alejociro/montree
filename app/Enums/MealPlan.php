<?php

declare(strict_types=1);

namespace App\Enums;

/** Qué comidas cubre la tarifa del hotel. */
enum MealPlan: string
{
    case BreakfastOnly = 'breakfast_only';
    case HalfBoard = 'half_board';
    case FullBoard = 'full_board';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::BreakfastOnly => __('Breakfast only'),
            self::HalfBoard => __('Half board'),
            self::FullBoard => __('Full board'),
            self::None => __('No meals'),
        };
    }
}
