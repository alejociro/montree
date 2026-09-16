<?php

declare(strict_types=1);

namespace App\Actions\Tour;

use App\Data\Tour\RouteData;
use App\Models\Route;
use App\Models\Tour;
use Illuminate\Support\Facades\DB;

/**
 * Guarda una ruta del producto y, si el formulario mandó paradas, las reescribe.
 *
 * Las paradas se borran y se vuelven a crear en vez de casarlas por id: la
 * lista es corta, el orden es el dato —define el recorrido— y reordenarla a
 * base de `update` deja huecos de posición en cuanto alguien arrastra dos filas.
 */
final class SaveRouteAction
{
    public function __construct(private SetDefaultRouteAction $setDefault) {}

    public function execute(Tour $tour, RouteData $data, ?Route $route = null): Route
    {
        return DB::transaction(function () use ($tour, $data, $route): Route {
            $route = $route === null
                ? $tour->routes()->create($data->attributes)
                : tap($route)->update($data->attributes);

            if (is_array($data->stops)) {
                $this->syncStops($route, $data->stops);
            }

            if ($data->isDefault === true) {
                $this->setDefault->execute($route);
            }

            return $route->fresh(['stops']) ?? $route;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $stops
     */
    private function syncStops(Route $route, array $stops): void
    {
        $route->stops()->delete();

        foreach (array_values($stops) as $index => $stop) {
            $route->stops()->create([
                'position' => $index + 1,
                'name' => (string) $stop['name'],
                'kind' => (string) $stop['kind'],
                'latitude' => $stop['latitude'] ?? null,
                'longitude' => $stop['longitude'] ?? null,
                'time_label' => $this->trimmedOrNull($stop['time_label'] ?? null),
            ]);
        }
    }

    private function trimmedOrNull(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}
