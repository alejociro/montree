<?php

declare(strict_types=1);

namespace App\Enums;

/** Hasta cuándo se puede soltar una reserva de hotel sin pagarla. */
enum CancellationPolicy: string
{
    case Free48Hours = 'free_48_hours';
    case Free7Days = 'free_7_days';
    case NonRefundable = 'non_refundable';
    case PerContract = 'per_contract';

    public function label(): string
    {
        return match ($this) {
            self::Free48Hours => __('Free up to 48 hours before'),
            self::Free7Days => __('Free up to 7 days before'),
            self::NonRefundable => __('Non refundable'),
            self::PerContract => __('As agreed in the contract'),
        };
    }
}
