<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipos de documento de un pasajero.
 *
 * WHY: sin `nit` — el pasajero es una persona, no una empresa. Son los cinco
 * valores que `BookingTravelersSection.vue` ya ofrece escritos a mano.
 */
enum DocumentType: string
{
    case Cc = 'cc';
    case Ce = 'ce';
    case Ti = 'ti';
    case Passport = 'passport';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cc => __('Cédula de ciudadanía'),
            self::Ce => __('Cédula de extranjería'),
            self::Ti => __('Tarjeta de identidad'),
            self::Passport => __('Pasaporte'),
            self::Other => __('Otro'),
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
