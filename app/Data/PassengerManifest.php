<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\BookingTraveler;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Resultado de la planilla: las filas que pasaron el filtro y el resumen que
 * pinta el pie de la tabla, calculado sobre esas mismas filas.
 */
final readonly class PassengerManifest
{
    /**
     * @param  Collection<int, BookingTraveler>  $rows
     * @param  array{total_passengers: int, with_due: int, paid: int, with_notes: int, total_due_amount: string, currency: string}  $summary
     */
    public function __construct(
        public Collection $rows,
        public array $summary,
    ) {}

    /**
     * @return LengthAwarePaginator<int, BookingTraveler>
     */
    public function paginate(int $perPage, int $page): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            $this->rows->forPage($page, $perPage)->values(),
            $this->rows->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }
}
