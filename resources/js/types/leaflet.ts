/**
 * Superficie mínima de Leaflet 1.9 que usa el mapa de ruta.
 * El script se carga desde CDN en `useLeaflet`, así que no hay `@types/leaflet`:
 * solo se declara lo que se consume, para no romper `strict` con `any`.
 */

export type LatLngTuple = [number, number];

export interface LeafletLayer {
    addTo(map: LeafletMap): this;
}

export interface LeafletDivIcon {
    readonly options: { readonly className: string };
}

export interface LeafletMarker extends LeafletLayer {
    bindPopup(content: string): this;
    openPopup(): this;
    on(event: 'click', handler: () => void): this;
}

export type LeafletPolyline = LeafletLayer;

export type LeafletCircle = LeafletLayer;

export type LeafletTileLayer = LeafletLayer;

export interface LeafletLatLngBounds {
    pad(bufferRatio: number): LeafletLatLngBounds;
}

export interface LeafletZoomHandler {
    enable(): void;
    disable(): void;
}

export interface LeafletMap {
    scrollWheelZoom: LeafletZoomHandler;
    remove(): void;
    removeLayer(layer: LeafletLayer): this;
    fitBounds(bounds: LeafletLatLngBounds): this;
    flyTo(
        center: LatLngTuple,
        zoom: number,
        options?: { duration: number },
    ): this;
    invalidateSize(): this;
    on(event: 'click' | 'mouseout', handler: () => void): this;
}

export interface LeafletNamespace {
    map(
        element: HTMLElement,
        options: { scrollWheelZoom: boolean; zoomControl: boolean },
    ): LeafletMap;
    tileLayer(
        urlTemplate: string,
        options: { maxZoom: number; attribution: string },
    ): LeafletTileLayer;
    polyline(
        latlngs: LatLngTuple[],
        options: { color: string; weight: number; dashArray?: string },
    ): LeafletPolyline;
    circle(
        center: LatLngTuple,
        options: {
            radius: number;
            color: string;
            weight: number;
            fillColor: string;
            fillOpacity: number;
        },
    ): LeafletCircle;
    marker(
        latlng: LatLngTuple,
        options: { icon: LeafletDivIcon; zIndexOffset: number },
    ): LeafletMarker;
    divIcon(options: {
        className: string;
        iconSize: [number, number];
        iconAnchor: [number, number];
        html: string;
    }): LeafletDivIcon;
    latLngBounds(latlngs: LatLngTuple[]): LeafletLatLngBounds;
}

declare global {
    interface Window {
        L?: LeafletNamespace;
    }
}
