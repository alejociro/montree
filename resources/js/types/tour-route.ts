export type TourRouteStopKind = 'pickup' | 'site' | 'drop';

export type TourRouteStop = {
    kind: TourRouteStopKind;
    /** Glifo del pin: `A` para recogida, `1..n` para paradas, `B` para regreso. */
    code: string;
    /** Etiqueta impresa junto al pin. Solo recogida y regreso la llevan. */
    label: string | null;
    name: string;
    place: string | null;
    time: string | null;
    latitude: number;
    longitude: number;
};

/** Zona donde ocurre el tour, dibujada como círculo sobre el mapa. */
export type TourRouteZone = {
    latitude: number;
    longitude: number;
    radius_meters: number;
};

export type TourRouteView = 'all' | 'pickup' | 'site' | 'drop';

export type TourRouteMapStatus = 'loading' | 'ready' | 'error';
