<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipos de documento de un pasajero.
 *
 * WHY: sin `nit` — el pasajero es una persona, no una empresa.
 *
 * En la planilla el tipo va ABREVIADO (CC, CE, TI, SI, PA): escrito completo
 * —«Cédula de ciudadanía»— parte la celda en tres líneas y tapa el número, que
 * es el dato con el que el guía identifica a la persona. La abreviatura vive
 * aquí, junto a la etiqueta larga, para que no se inventen dos tablas.
 */
enum DocumentType: string
{
    case Cc = 'cc';
    case Ce = 'ce';
    case Ti = 'ti';
    case Sisben = 'sisben';
    case Passport = 'passport';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cc => __('Cédula de ciudadanía'),
            self::Ce => __('Cédula de extranjería'),
            self::Ti => __('Tarjeta de identidad'),
            self::Sisben => __('Sisben'),
            self::Passport => __('Pasaporte'),
            self::Other => __('Otro'),
        };
    }

    /** Abreviatura para la planilla y el CSV. */
    public function abbreviation(): string
    {
        return match ($this) {
            self::Cc => 'CC',
            self::Ce => 'CE',
            self::Ti => 'TI',
            self::Sisben => 'SI',
            self::Passport => 'PA',
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
