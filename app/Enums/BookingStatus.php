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

    /**
     * Reserva viva: la que todavía puede llevar gente a un tour. Es la misma
     * lista que usan el borrado del tour y el cambio de fecha de una salida, y
     * la que decide a quién se avisa cuando cambia una parada de recogida.
     *
     * @return array<int, string>
     */
    public static function activeValues(): array
    {
        return [
            self::PendingPayment->value,
            self::Confirmed->value,
        ];
    }

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
