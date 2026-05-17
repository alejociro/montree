<?php

declare(strict_types=1);

namespace App\Enums;

enum TourDifficulty: string
{
    case Easy = 'easy';
    case Moderate = 'moderate';
    case Hard = 'hard';
    case Extreme = 'extreme';

    public function label(): string
    {
        return match ($this) {
            self::Easy => 'Easy',
            self::Moderate => 'Moderate',
            self::Hard => 'Hard',
            self::Extreme => 'Extreme',
        };
    }
}
