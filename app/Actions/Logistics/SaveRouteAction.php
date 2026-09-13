<?php

declare(strict_types=1);

namespace App\Actions\Logistics;

use App\Models\Route;
use Illuminate\Support\Facades\DB;

/**
 * Guarda una ruta y, si el formulario mandó paradas, las reescribe.
 *
 * Las paradas se borran y se vuelven a crear en vez de casarlas por id: la
 * lista es corta, el orden es el dato —define el recorrido— y reordenarla a
 * base de `update` deja huecos de posición en cuanto alguien arrastra dos filas.
 */
final class SaveRouteAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Route $route, array $data): Route
    {
        $stops = $data['stops'] ?? null;
        unset($data['stops']);

        return DB::transaction(function () use ($route, $data, $stops): Route {
            $route = $route === null ? Route::create($data) : tap($route)->update($data);

            if (is_array($stops)) {
                $this->syncStops($route, $stops);
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
