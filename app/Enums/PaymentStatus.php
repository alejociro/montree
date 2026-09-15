<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Refunded = 'refunded';

    /**
     * WHY: el idioma de origen del proyecto es el español y `lang/en.json`
     * traduce de ahí. Con la cadena en inglés dentro de `__()` el panel en
     * español mostraba «Completed» en los chips y en el filtro de transacciones.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pendiente'),
            self::Processing => __('En proceso'),
            self::Completed => __('Completado'),
            self::Failed => __('Fallido'),
            self::Refunded => __('Reembolsado'),
        };
    }
}
