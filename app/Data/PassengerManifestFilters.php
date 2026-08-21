<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\BookingStatus;

/**
 * Filtros de la planilla de pasajeros. Los comparten las dos zonas (panel y
 * guía) y las cuatro entradas (index y export de cada una).
 */
final readonly class PassengerManifestFilters
{
    public const SEGMENTS = ['all', 'due', 'paid', 'obs'];

    /**
     * @param  array<int, BookingStatus>  $statuses
     */
    public function __construct(
        public string $segment,
        public ?string $search,
        public array $statuses,
        public string $fallbackCurrency,
        public int $perPage = 50,
    ) {}

    /**
     * @return array<int, string>
     */
    public function statusValues(): array
    {
        return array_map(static fn (BookingStatus $status): string => $status->value, $this->statuses);
    }
}
