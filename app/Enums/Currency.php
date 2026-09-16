<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Monedas que una agencia puede operar. Una sola por tenant: el catálogo, las
 * reservas y las tarifas de logística heredan la que está en
 * `tenant_configurations.currency`.
 */
enum Currency: string
{
    case Usd = 'USD';
    case Cop = 'COP';
    case Eur = 'EUR';
    case Mxn = 'MXN';
    case Ars = 'ARS';
    case Pen = 'PEN';
    case Clp = 'CLP';
    case Brl = 'BRL';

    public const FALLBACK = 'COP';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Usd => __('Dólar estadounidense (USD)'),
            self::Cop => __('Peso colombiano (COP)'),
            self::Eur => __('Euro (EUR)'),
            self::Mxn => __('Peso mexicano (MXN)'),
            self::Ars => __('Peso argentino (ARS)'),
            self::Pen => __('Sol peruano (PEN)'),
            self::Clp => __('Peso chileno (CLP)'),
            self::Brl => __('Real brasileño (BRL)'),
        };
    }
}
