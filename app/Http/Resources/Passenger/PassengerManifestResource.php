<?php

declare(strict_types=1);

namespace App\Http\Resources\Passenger;

use App\Data\PassengerManifest;
use App\Models\TourDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Respuesta completa de la planilla: filas paginadas más el `meta` que el
 * frontend necesita para saber qué pintar. La comparten el panel y la zona del
 * guía sin diferencias.
 */
final class PassengerManifestResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @param  Collection<int, TourDate>  $departures
     */
    public function __construct(
        private readonly PassengerManifest $manifest,
        private readonly Collection $departures,
        private readonly int $perPage,
        private readonly int $page,
    ) {
        parent::__construct($manifest);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $paginator = $this->manifest->paginate($this->perPage, $this->page);

        return [
            'data' => PassengerResource::collection($paginator->items())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'can_view_medical' => PassengerResource::canViewMedical($request),
                'summary' => (new PassengerManifestSummary($this->manifest->summary))->resolve($request),
                'departures' => DepartureOptionResource::collection($this->departures)->resolve($request),
            ],
        ];
    }
}
