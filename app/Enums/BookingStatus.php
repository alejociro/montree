<?php

declare(strict_types=1);

namespace App\Enums;

enum BookingStatus: string
{
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case Refunded = 'refunded';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => __('Pending payment'),
            self::Confirmed => __('Confirmed'),
            self::Cancelled => __('Cancelled'),
            self::Completed => __('Completed'),
            self::Refunded => __('Refunded'),
            self::Expired => __('Expired'),
        };
    }
}
