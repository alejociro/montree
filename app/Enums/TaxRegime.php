<?php

declare(strict_types=1);

namespace App\Enums;

/** Régimen tributario del proveedor: lo necesita contabilidad para pagarle. */
enum TaxRegime: string
{
    case VatResponsible = 'vat_responsible';
    case VatExempt = 'vat_exempt';
    case SimpleRegime = 'simple_regime';

    public function label(): string
    {
        return match ($this) {
            self::VatResponsible => __('VAT responsible'),
            self::VatExempt => __('Not VAT responsible'),
            self::SimpleRegime => __('Simple regime'),
        };
    }
}
