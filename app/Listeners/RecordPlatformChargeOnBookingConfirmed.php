<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Platform\RecordPlatformChargeAction;
use App\Events\BookingConfirmed;

final class RecordPlatformChargeOnBookingConfirmed
{
    public function __construct(private RecordPlatformChargeAction $recordCharge) {}

    public function handle(BookingConfirmed $event): void
    {
        $this->recordCharge->execute($event->booking, $event->payment);
    }
}
