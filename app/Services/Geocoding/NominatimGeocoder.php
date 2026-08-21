<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Busca un punto del mapa a partir de una dirección o del nombre de un lugar.
 *
 * WHY el proxy: la búsqueda podría salir del navegador, pero Nominatim exige un
 * User-Agent identificable y limita a una petición por segundo POR CLIENTE.
 * Desde el front eso no se puede garantizar —cada tecleo de cada agencia sería
 * una petición anónima— y el servicio termina bloqueando la IP. Aquí sale una
 * sola vez, identificada, y la respuesta queda en caché: la misma dirección no
 * vuelve a la red durante un día.
 *
 * Que falle NO puede tumbar el formulario: sin red se devuelve una lista vacía
 * y la agencia sigue pudiendo marcar el punto arrastrando el pin en el mapa.
 */
final class NominatimGeocoder
{
    private const MAX_RESULTS = 6;

    /**
     * @return Collection<int, GeocodedPlace>
     */
    public function search(string $query): Collection
    {
        $term = trim($query);

        if (mb_strlen($term) < 3) {
            return collect();
        }

        $ttl = (int) config('montree.geocoding.cache_ttl');

        /** @var Collection<int, GeocodedPlace> */
        return Cache::remember(
            'geocode:'.md5(mb_strtolower($term).'|'.app()->getLocale()),
            $ttl,
            fn (): Collection => $this->fetch($term),
        );
    }

    /**
     * @return Collection<int, GeocodedPlace>
     */
    private function fetch(string $term): Collection
    {
        $parameters = [
            'q' => $term,
            'format' => 'jsonv2',
            'addressdetails' => 0,
            'limit' => self::MAX_RESULTS,
            'accept-language' => app()->getLocale(),
        ];

        $countries = (string) config('montree.geocoding.country_codes');

        if ($countries !== '') {
            $parameters['countrycodes'] = $countries;
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => (string) config('montree.geocoding.user_agent'),
            ])
                ->timeout((int) config('montree.geocoding.timeout'))
                ->get((string) config('montree.geocoding.endpoint'), $parameters);
        } catch (Throwable $exception) {
            Log::warning('Geocoder unreachable', ['message' => $exception->getMessage()]);

            return collect();
        }

        if ($response->failed()) {
            Log::warning('Geocoder responded with an error', ['status' => $response->status()]);

            return collect();
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return collect();
        }

        return collect($payload)
            ->filter(static fn (mixed $row): bool => is_array($row))
            ->map(static fn (array $row): ?GeocodedPlace => GeocodedPlace::fromNominatim($row))
            ->filter()
            ->values();
    }
}
