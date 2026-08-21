<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\TourDateStatus;
use Illuminate\Support\Carbon;

/**
 * Un bloque de días calendario que una salida le ocupa a un guía (D9).
 *
 * WHY: la ocupación se mide en días completos, no en horas. Un tour de cinco
 * días le bloquea los cinco aunque el último termine a las nueve de la mañana,
 * así que el bloque viaja como dos fechas `Y-m-d` y no como dos instantes.
 */
final readonly class GuideBusyBlock
{
    /** Abreviaturas fijas: el motivo se muestra igual sea cual sea el locale del panel. */
    private const MONTHS = [
        1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun',
        7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic',
    ];

    public function __construct(
        public int $tourDateId,
        public string $tourName,
        public string $from,
        public string $to,
        public TourDateStatus $status,
    ) {}

    /**
     * El motivo que ve el administrador: «12–14 sep · Valle de Cocora».
     */
    public function label(): string
    {
        return $this->range().' · '.$this->tourName;
    }

    /**
     * @return array{tour_date_id:int, tour_name:string, from:string, to:string, status:string}
     */
    public function toArray(): array
    {
        return [
            'tour_date_id' => $this->tourDateId,
            'tour_name' => $this->tourName,
            'from' => $this->from,
            'to' => $this->to,
            'status' => $this->status->value,
        ];
    }

    private function range(): string
    {
        $from = Carbon::parse($this->from);
        $to = Carbon::parse($this->to);

        if ($from->isSameDay($to)) {
            return $from->day.' '.self::MONTHS[$from->month];
        }

        if ($from->month === $to->month && $from->year === $to->year) {
            return $from->day.'–'.$to->day.' '.self::MONTHS[$to->month];
        }

        return $from->day.' '.self::MONTHS[$from->month].' – '.$to->day.' '.self::MONTHS[$to->month];
    }
}
