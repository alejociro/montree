/** Espejo de `App\Services\Geocoding\GeocodedPlace`. */
export type GeocodedPlace = {
    /** Trozo corto: rellena el nombre de la parada. */
    name: string;
    /** Línea larga que se muestra en la lista de resultados. */
    label: string;
    latitude: number;
    longitude: number;
};

export type GeocodeResponse = {
    data: GeocodedPlace[];
};

/** Un punto del mapa tal como lo maneja el editor de ruta. */
export type MapPoint = {
    latitude: number;
    longitude: number;
};
