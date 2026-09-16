<?php

declare(strict_types=1);

namespace App\Actions\Tour;

use App\Data\Tour\TourRoutesData;
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
        $tour->unsetRelation('routes');
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
