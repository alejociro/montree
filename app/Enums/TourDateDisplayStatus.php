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
            self::Open => 'Abierta',
            self::Full => 'Llena',
            self::Closed => 'Cerrada',
            self::Cancelled => 'Cancelada',
            self::InProgress => 'En curso',
            self::Finished => 'Finalizada',
        };
    }
}
