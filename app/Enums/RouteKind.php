<?php

declare(strict_types=1);

namespace App\Enums;

/** Cómo se recorre una ruta: define el equipo y el proveedor que necesita. */
enum RouteKind: string
{
    case Hiking = 'hiking';
    case Land = 'land';
    case Mixed = 'mixed';
    case Water = 'water';
    case Cycling = 'cycling';

    public function label(): string
    {
        return match ($this) {
            self::Hiking => __('Hiking'),
            self::Land => __('Land'),
            self::Mixed => __('Mixed'),
            self::Water => __('Water'),
            self::Cycling => __('Cycling'),
        };
    }
}
