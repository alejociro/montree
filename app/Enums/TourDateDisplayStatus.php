<?php

declare(strict_types=1);

namespace App\Enums;

enum TourDateDisplayStatus: string
{
    case Open = 'open';
    case Full = 'full';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
    case InProgress = 'in_progress';
    case Finished = 'finished';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Abierta'),
            self::Full => __('Llena'),
            self::Closed => __('Cerrada'),
            self::Cancelled => __('Cancelada'),
            self::InProgress => __('En curso'),
            self::Finished => __('Finalizada'),
        };
    }
}
