<?php

declare(strict_types=1);

namespace App\Queries;

use App\Data\DepartureDefaults;
use App\Http\Resources\Admin\RouteResource;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Productos + lo que una salida nueva hereda de cada uno (spec §G / T9).
 *
 * WHY: el tablero de salidas (`DeparturePagesController`) y las páginas paso a
 * paso de crear/editar salida (`DepartureFormPagesController`) necesitan
 * exactamente el mismo shape —rutas del producto y `DepartureDefaults`— así
 * que vivía como método privado duplicado. Ahora es una sola consulta que la
 * página de creación desde el tablero pide completa (`all()`) y las páginas
 * que ya conocen el producto piden de a uno (`forTour()`), sin traer el resto
 * del catálogo que no van a mostrar.
 */
final class DepartureTourOptionsQuery
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return Tour::query()
            ->with($this->eagerLoads())
            ->orderBy('name')
            ->get()
            ->map(fn (Tour $tour) => $this->toArray($tour))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function forTour(Tour $tour): array
    {
        $tour->loadMissing($this->eagerLoads());

        return $this->toArray($tour);
    }

    /**
     * @return array<string, \Closure|string>
     */
    private function eagerLoads(): array
    {
        return [
            'routes' => fn (HasMany $query) => $query->withCount('stops'),
            'itineraries',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Tour $tour): array
    {
        return [
            'id' => $tour->id,
            'name' => $tour->name,
            // La salida deriva su fin de la duración del producto.
            'duration_hours' => $tour->duration_hours,
            'routes' => RouteResource::collection($tour->routes)->resolve(),
            'departure_defaults' => DepartureDefaults::fromTour($tour, $this->configuration())->toArray(),
        ];
    }

    private function configuration(): ?TenantConfiguration
    {
        return Tenant::current()?->configuration;
    }
}
