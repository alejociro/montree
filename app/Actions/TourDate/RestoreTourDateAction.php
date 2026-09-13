<?php

declare(strict_types=1);

namespace App\Actions\TourDate;

use App\Enums\TourDateStatus;
use App\Exceptions\TourDateException;
use App\Models\TourDate;
use App\Queries\DepartureBoardQuery;

/**
 * Vuelve a habilitar una salida inhabilitada.
 *
 * WHY: inhabilitar era un camino de una sola vía —quedaba `cancelled` para
 * siempre— y la única salida era crear otra fecha, que pierde el histórico y
 * las reservas. La reactivación no adivina el estado: si el cupo ya está
 * copado vuelve como `full`, no como `open`.
 */
final class RestoreTourDateAction
{
    public function __construct(private DepartureBoardQuery $board) {}

    public function handle(TourDate $tourDate): TourDate
    {
        if ($tourDate->status !== TourDateStatus::Cancelled) {
            throw TourDateException::notCancelled();
        }

        $tourDate->status = $this->board->statusAfterRestore($tourDate);
        $tourDate->save();

        return $tourDate->fresh() ?? $tourDate;
    }
}
