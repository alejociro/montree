import type * as Leaflet from 'leaflet';

/**
 * Alias de los tipos de Leaflet que consume el mapa de ruta.
 * La librería llega por npm y se carga con `import()` dinámico en `useLeaflet`,
 * así que el namespace se tipa como el módulo entero.
 */
export type LeafletNamespace = typeof Leaflet;

export type {
    DivIcon as LeafletDivIcon,
    LatLngTuple,
    Map as LeafletMap,
    Marker as LeafletMarker,
} from 'leaflet';
