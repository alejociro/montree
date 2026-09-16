<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Platform\RecordPlatformChargeAction;
use App\Events\BookingConfirmed;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * WHY: el cobro de la plataforma NO puede correr dentro de la transacción del
 * pago. `BookingSettlementService` se ejecuta con la reserva bloqueada desde el
 * `DB::transaction()` de quien liquida, así que una excepción acá revertía el
 * pago entero: el dinero ya entró y la reserva tiene que quedar confirmada
 * aunque el cargo falle. Encolado y `afterCommit`, el cargo sale del camino
 * crítico y se reintenta solo.
 */
final class RecordPlatformChargeOnBookingConfirmed implements ShouldQueue
{
    public bool $afterCommit = true;

    public int $tries = 3;

    public function __construct(private RecordPlatformChargeAction $recordCharge) {}

    public function handle(BookingConfirmed $event): void
    {
        $this->recordCharge->execute($event->booking, $event->payment);
    }
}
