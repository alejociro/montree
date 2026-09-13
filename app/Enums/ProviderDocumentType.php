<?php

declare(strict_types=1);

namespace App\Enums;

/** Documentos del proveedor cuyo vencimiento se vigila antes de cada salida. */
enum ProviderDocumentType: string
{
    case LiabilityPolicy = 'liability_policy';
    case TaxRegistry = 'tax_registry';
    case ChamberOfCommerce = 'chamber_of_commerce';
    case HealthRegistry = 'health_registry';
    case OperationCard = 'operation_card';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::LiabilityPolicy => __('Liability insurance'),
            self::TaxRegistry => __('Tax registry'),
            self::ChamberOfCommerce => __('Chamber of commerce'),
            self::HealthRegistry => __('Health registry'),
            self::OperationCard => __('Operation card'),
            self::Other => __('Other'),
        };
    }
}
