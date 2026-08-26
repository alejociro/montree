<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

/**
 * Un resultado de búsqueda de dirección, ya normalizado.
 *
 * `label` es la línea larga que se muestra en la lista de resultados y `name`
 * el trozo corto con el que se rellena el nombre de la parada, para que quien
 * programa el tour no tenga que recortar «Plaza de Bolívar, Salento, Quindío,
 * Colombia» a mano cada vez.
 *
 * `city` y `state` salen de `addressdetails` del proveedor, no de partir la
 * línea larga por comas: en «Salento, Fría, Quindío, RAP Eje Cafetero,
 * Colombia» el segundo trozo es un barrio, no el municipio.
 */
final readonly class GeocodedPlace
{
    public function __construct(
        public string $name,
        public string $label,
        public float $latitude,
        public float $longitude,
        public ?string $city = null,
        public ?string $state = null,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromNominatim(array $raw): ?self
    {
        $label = is_string($raw['display_name'] ?? null) ? $raw['display_name'] : null;
        $latitude = isset($raw['lat']) && is_numeric($raw['lat']) ? (float) $raw['lat'] : null;
        $longitude = isset($raw['lon']) && is_numeric($raw['lon']) ? (float) $raw['lon'] : null;

        if ($label === null || $latitude === null || $longitude === null) {
            return null;
        }

        $name = is_string($raw['name'] ?? null) && $raw['name'] !== ''
            ? $raw['name']
            : trim(explode(',', $label)[0]);

        $address = is_array($raw['address'] ?? null) ? $raw['address'] : [];

        return new self(
            $name,
            $label,
            $latitude,
            $longitude,
            self::firstString($address, ['city', 'town', 'village', 'municipality', 'county']),
            self::firstString($address, ['state', 'region', 'province']),
        );
    }

    /**
     * @param  array<string, mixed>  $address
     * @param  list<string>  $keys
     */
    private static function firstString(array $address, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (is_string($address[$key] ?? null) && $address[$key] !== '') {
                return $address[$key];
            }
        }

        return null;
    }

    /**
     * @return array{name: string, label: string, latitude: float, longitude: float, city: string|null, state: string|null}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'city' => $this->city,
            'state' => $this->state,
        ];
    }
}
