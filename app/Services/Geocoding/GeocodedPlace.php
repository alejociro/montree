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
 */
final readonly class GeocodedPlace
{
    public function __construct(
        public string $name,
        public string $label,
        public float $latitude,
        public float $longitude,
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

        return new self($name, $label, $latitude, $longitude);
    }

    /**
     * @return array{name: string, label: string, latitude: float, longitude: float}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }
}
