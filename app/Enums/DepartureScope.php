<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Bandejas del tablero de salidas. No es el estado guardado —eso es
 * TourDateStatus— sino el corte por el que el usuario navega: qué viene, qué
 * pasa hoy, qué ya ocurrió y qué está inhabilitado.
 */
enum DepartureScope: string
{
    case Upcoming = 'upcoming';
    case Today = 'today';
    case Past = 'past';
    case Disabled = 'disabled';
    case All = 'all';

    public function label(): string
    {
        return match ($this) {
            self::Upcoming => __('Próximas'),
            self::Today => __('Hoy'),
            self::Past => __('Realizadas'),
            self::Disabled => __('Inhabilitadas'),
            self::All => __('Todas'),
        };
    }
}
