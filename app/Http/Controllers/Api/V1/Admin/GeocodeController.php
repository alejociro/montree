<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Geocoding\GeocodeSearchRequest;
use App\Services\Geocoding\GeocodedPlace;
use App\Services\Geocoding\NominatimGeocoder;
use Illuminate\Http\JsonResponse;

/**
 * Direcciones y lugares para el editor de ruta del tour. Quien programa una
 * salida sabe dónde recoge a la gente, no en qué latitud queda: escribe la
 * dirección y esto devuelve el punto.
 */
final class GeocodeController extends Controller
{
    public function __construct(private NominatimGeocoder $geocoder) {}

    public function __invoke(GeocodeSearchRequest $request): JsonResponse
    {
        $places = $this->geocoder->search($request->term());

        return new JsonResponse([
            'data' => $places
                ->map(static fn (GeocodedPlace $place): array => $place->toArray())
                ->values(),
        ]);
    }
}
