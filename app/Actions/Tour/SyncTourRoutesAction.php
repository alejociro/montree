<?php

declare(strict_types=1);

namespace App\Actions\Tour;

use App\Data\Tour\TourRoutesData;
use App\Enums\TourDateStatus;
use App\Models\Tour;

/**
 * Reescribe las rutas de un producto respetando el orden recibido.
 *
 * La marca de predeterminada se resuelve aquí y no en la base: `route_tour` no
 * puede expresar «un solo true por tour» de forma portable entre MySQL y SQLite.
 * Sin marca explícita el producto queda sin predeterminada: la salida nueva
 * arranca entonces sin ruta preseleccionada (spec, edge cases).
 */
final class SyncTourRoutesAction
{
    public function execute(Tour $tour, TourRoutesData $routes): void
    {
        $default = $this->defaultRouteId($routes);

        $payload = [];

        foreach ($routes->assignments as $position => $assignment) {
            $payload[$assignment->routeId] = [
                'is_default' => $assignment->routeId === $default,
                'position' => $position + 1,
            ];
        }

        $tour->routes()->sync($payload);
        $this->clearOrphanedDepartureRoutes($tour, array_keys($payload));
        $tour->unsetRelation('routes');
    }

    /**
     * Desasociar una ruta del producto dejaba `route_id` apuntando a una ruta que
     * el producto ya no ofrece. Se limpia solo en las salidas que todavía se
     * pueden operar: una salida pasada o cancelada es historia y su ruta es el
     * dato de lo que se hizo (spec, edge cases).
     *
     * @param  array<int, int>  $keptRouteIds
     */
    private function clearOrphanedDepartureRoutes(Tour $tour, array $keptRouteIds): void
    {
        $tour->dates()
            ->whereNotNull('route_id')
            ->whereNotIn('route_id', $keptRouteIds)
            ->where('starts_at', '>', now())
            ->where('status', '!=', TourDateStatus::Cancelled)
            ->update(['route_id' => null]);
    }

    private function defaultRouteId(TourRoutesData $routes): ?int
    {
        foreach ($routes->assignments as $assignment) {
            if ($assignment->isDefault) {
                return $assignment->routeId;
            }
        }

        return null;
    }
}
