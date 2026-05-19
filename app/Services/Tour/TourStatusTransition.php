<?php

declare(strict_types=1);

namespace App\Services\Tour;

use App\Enums\TourStatus;

final class TourStatusTransition
{
    /**
     * @var array<string, array<int, TourStatus>>
     */
    private const MATRIX = [
        'draft' => [TourStatus::Active, TourStatus::Archived],
        'active' => [TourStatus::Paused, TourStatus::Archived],
        'paused' => [TourStatus::Active, TourStatus::Archived],
        'archived' => [TourStatus::Draft],
    ];

    public function isValid(TourStatus $from, TourStatus $to): bool
    {
        if ($from === $to) {
            return false;
        }

        return in_array($to, self::MATRIX[$from->value] ?? [], true);
    }

    public function requiresAdmin(TourStatus $to): bool
    {
        return $to === TourStatus::Archived;
    }
}
