<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Ambiente de checkout de PlacetoPay para un comercio (propio o de
 * plataforma). La URL vive en `config('placetopay.environments')`, no
 * hardcodeada acá, para no duplicar la fuente de verdad del endpoint.
 */
enum PlaceToPayEnvironment: string
{
    case Test = 'test';
    case Production = 'production';

    public function url(): string
    {
        return (string) config('placetopay.environments.'.$this->value);
    }

    public function label(): string
    {
        return match ($this) {
            self::Test => __('Pruebas (sandbox)'),
            self::Production => __('Producción (cobros reales)'),
        };
    }
}
