<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Módulo del producto que se puede apagar entero desde la configuración.
 *
 * WHY: apagado NO es "sin permiso". Un módulo apagado no existe para nadie —
 * ruta 404, ítem fuera del menú, permiso fuera del catálogo— sin borrar su
 * código ni sus filas en `permissions`, para que encenderlo vuelva a ser una
 * variable de entorno y no una migración.
 */
enum Module: string
{
    case Newsletter = 'newsletter';
    case Promotions = 'promotions';
    case Logistics = 'logistics';

    public function isEnabled(): bool
    {
        return (bool) config('montree.modules.'.$this->value);
    }

    /** El módulo está apagado. Una clave que no es un módulo desactivable nunca lo está. */
    public static function isDisabled(string $key): bool
    {
        return self::tryFrom($key)?->isEnabled() === false;
    }

    /**
     * Estado de cada módulo, tal como viaja a Inertia.
     *
     * @return array<string, bool>
     */
    public static function flags(): array
    {
        $flags = [];

        foreach (self::cases() as $module) {
            $flags[$module->value] = $module->isEnabled();
        }

        return $flags;
    }
}
