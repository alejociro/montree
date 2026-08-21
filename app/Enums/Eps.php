<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * EPS del pasajero. Dato sensible: solo se serializa con el permiso
 * `bookings.passengers.medical.view`.
 */
enum Eps: string
{
    case Sura = 'sura';
    case NuevaEps = 'nueva_eps';
    case Sanitas = 'sanitas';
    case SaludTotal = 'salud_total';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Sura => __('Sura'),
            self::NuevaEps => __('Nueva EPS'),
            self::Sanitas => __('Sanitas'),
            self::SaludTotal => __('Salud Total'),
            self::Other => __('Otra'),
        };
    }

    /**
     * `other` es el único caso que exige el texto libre `eps_other`.
     */
    public function requiresDetail(): bool
    {
        return $this === self::Other;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
