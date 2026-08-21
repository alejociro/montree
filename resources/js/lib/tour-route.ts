import { translate } from '@/composables/useTranslations';
import type { LatLngTuple } from '@/types/leaflet';
import type { TourStopDraft } from '@/types/tour';
import type { TourDetail } from '@/types/tour-detail';
import type { TourRouteStop, TourRouteZone } from '@/types/tour-route';

/**
 * Colores del mapa. Viven en los tokens `--brand-*` de `app.css`, pero
 * Leaflet los escribe como atributos de presentación SVG (`stroke`, `fill`),
 * donde `var()` no se resuelve: hay que entregar el valor computado.
 * El fallback es la paleta del handoff, para SSR y para el primer render.
 */
const ROUTE_COLOR_TOKENS = {
    // D5: la recogida es el pin principal y va por `--primary`, el color del
    // tenant. Los demás roles son semánticos y se quedan en tokens fijos.
    pickup: ['--primary', '#2f6b45'],
    site: ['--brand-site', '#4b7f5f'],
    drop: ['--brand-drop', '#b4562a'],
    transfer: ['--brand-muted', '#6d7268'],
    zone: ['--brand-green', '#1d4630'],
} as const;

export type TourRouteColorRole = keyof typeof ROUTE_COLOR_TOKENS;

const resolvedColors = new Map<TourRouteColorRole, string>();

export function routeColor(role: TourRouteColorRole): string {
    const cached = resolvedColors.get(role);

    if (cached !== undefined) {
        return cached;
    }

    const [token, fallback] = ROUTE_COLOR_TOKENS[role];
    const computed =
        typeof window === 'undefined'
            ? ''
            : window
                  .getComputedStyle(document.documentElement)
                  .getPropertyValue(token)
                  .trim();
    const color = computed === '' ? fallback : computed;

    resolvedColors.set(role, color);

    return color;
}

/** Radio mínimo de la zona del tour cuando se deriva de las paradas. */
const MIN_ZONE_RADIUS_METERS = 800;
const EARTH_RADIUS_METERS = 6_371_000;

export function stopCoordinates(stop: TourRouteStop): LatLngTuple {
    return [stop.latitude, stop.longitude];
}

export function stopColor(stop: TourRouteStop): string {
    return routeColor(stop.kind);
}

export function stopIndexesOfKind(
    stops: TourRouteStop[],
    kind: TourRouteStop['kind'],
): number[] {
    return stops.reduce<number[]>((indexes, stop, index) => {
        if (stop.kind === kind) {
            indexes.push(index);
        }

        return indexes;
    }, []);
}

function metersBetween(from: LatLngTuple, to: LatLngTuple): number {
    const toRadians = (degrees: number): number => (degrees * Math.PI) / 180;
    const deltaLat = toRadians(to[0] - from[0]);
    const deltaLng = toRadians(to[1] - from[1]);
    const haversine =
        Math.sin(deltaLat / 2) ** 2 +
        Math.cos(toRadians(from[0])) *
            Math.cos(toRadians(to[0])) *
            Math.sin(deltaLng / 2) ** 2;

    return (
        2 * EARTH_RADIUS_METERS * Math.asin(Math.min(1, Math.sqrt(haversine)))
    );
}

/**
 * Zona donde ocurre el tour: círculo que cubre las paradas del recorrido.
 * Solo se usa cuando la API no entrega una zona explícita.
 */
export function deriveZoneFromStops(
    stops: TourRouteStop[],
): TourRouteZone | null {
    const sites = stops.filter((stop) => stop.kind === 'site');

    if (sites.length < 2) {
        return null;
    }

    const center: LatLngTuple = [
        sites.reduce((sum, stop) => sum + stop.latitude, 0) / sites.length,
        sites.reduce((sum, stop) => sum + stop.longitude, 0) / sites.length,
    ];

    const radius = sites.reduce(
        (largest, stop) =>
            Math.max(largest, metersBetween(center, stopCoordinates(stop))),
        0,
    );

    return {
        latitude: center[0],
        longitude: center[1],
        radius_meters: Math.max(
            MIN_ZONE_RADIUS_METERS,
            Math.round(radius * 1.4),
        ),
    };
}

/** Indicaciones en Google Maps: origen, una parada intermedia y destino. */
export function googleDirectionsUrl(stops: TourRouteStop[]): string | null {
    if (stops.length < 2) {
        return null;
    }

    const origin = stops[0];
    const destination = stops[stops.length - 1];
    const waypoint = stops[Math.floor(stops.length / 2)];
    const asPair = (stop: TourRouteStop): string =>
        `${stop.latitude},${stop.longitude}`;

    const legs =
        waypoint === origin || waypoint === destination
            ? [origin, destination]
            : [origin, waypoint, destination];

    return `https://www.google.com/maps/dir/${legs.map(asPair).join('/')}`;
}

/** Lo que hace falta de un tour para dibujar su ruta: lo cumplen el detalle
 * público y el `TourResource` del panel. */
export type TourRouteSource = Pick<
    TourDetail,
    | 'name'
    | 'stops'
    | 'meeting_point'
    | 'meeting_latitude'
    | 'meeting_longitude'
>;

/**
 * Paradas de la ruta a partir del detalle público del tour.
 *
 * WHY: un tour puede no tener paradas cargadas todavía. En ese caso se cae al
 * punto de encuentro, que es el único dato geográfico que siempre existe, para
 * que el mapa muestre al menos la recogida en vez de desaparecer.
 */
export function routeStopsFromTour(tour: TourRouteSource): TourRouteStop[] {
    if (tour.stops.length > 0) {
        return tour.stops;
    }

    const latitude = Number.parseFloat(tour.meeting_latitude ?? '');
    const longitude = Number.parseFloat(tour.meeting_longitude ?? '');

    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
        return [];
    }

    return [
        {
            kind: 'pickup',
            code: 'A',
            label: translate('Recogida'),
            name: tour.meeting_point ?? tour.name,
            place: tour.meeting_point === null ? null : tour.name,
            time: null,
            latitude,
            longitude,
            itinerary_step: null,
        },
    ];
}

/** Índice de la parada que ilustra un paso del itinerario, si hay alguna. */
export function stopIndexForItineraryStep(
    stops: TourRouteStop[],
    stepNumber: number,
): number | null {
    const index = stops.findIndex((stop) => stop.itinerary_step === stepNumber);

    return index === -1 ? null : index;
}

/**
 * Paradas de la ruta a partir de los borradores que se están editando.
 *
 * WHY: el mapa de la pestaña «Ruta y mapa» tiene que reflejar lo que hay en el
 * formulario ahora, no lo último guardado. El `code` del pin se calcula con la
 * misma regla que `SyncTourStopsAction::codeFor()` —`A`, `1..n`, `B`— para que
 * la vista previa y el mapa público no digan cosas distintas. Las paradas sin
 * coordenadas válidas se omiten: no se pueden dibujar.
 */
export function routeStopsFromDrafts(drafts: TourStopDraft[]): TourRouteStop[] {
    const stops: TourRouteStop[] = [];
    let siteNumber = 0;

    for (const draft of drafts) {
        if (draft.kind === 'site') {
            siteNumber += 1;
        }

        const latitude = Number.parseFloat(draft.latitude);
        const longitude = Number.parseFloat(draft.longitude);

        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
            continue;
        }

        stops.push({
            kind: draft.kind,
            code:
                draft.kind === 'pickup'
                    ? 'A'
                    : draft.kind === 'drop'
                      ? 'B'
                      : String(siteNumber),
            label: draft.label.trim() === '' ? null : draft.label.trim(),
            name:
                draft.name.trim() === ''
                    ? translate('Sin nombre')
                    : draft.name.trim(),
            place: draft.place.trim() === '' ? null : draft.place.trim(),
            time: draft.time.trim() === '' ? null : draft.time.trim(),
            latitude,
            longitude,
            itinerary_step:
                draft.itinerary_step === ''
                    ? null
                    : Number(draft.itinerary_step) || null,
        });
    }

    return stops;
}
