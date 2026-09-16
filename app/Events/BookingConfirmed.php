<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;

final class BookingConfirmed
{
    use Dispatchable;

    public function __construct(
        public readonly Booking $booking,
        public readonly ?Payment $payment = null,
    ) {}
}
