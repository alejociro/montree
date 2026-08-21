import type { TourStopDraft, TourStopKind } from '@/types/tour';

/**
 * El orden con el que la ruta se dibuja en el mapa público: primero la
 * recogida, después el recorrido, al final el regreso.
 *
 * WHY aquí y no en el componente: la lista de paradas es un array plano y su
 * orden ES el trazo. Nada impedía guardar el regreso en medio del recorrido, y
 * el mapa salía en zigzag sin que el formulario dijera nada.
 */
const KIND_ORDER: Record<TourStopKind, number> = {
    pickup: 0,
    site: 1,
    drop: 2,
};

export function isStopOrderValid(stops: TourStopDraft[]): boolean {
    let highest = -1;

    for (const stop of stops) {
        const rank = KIND_ORDER[stop.kind];

        if (rank < highest) {
            return false;
        }

        highest = Math.max(highest, rank);
    }

    return true;
}

/**
 * Reordena por tipo conservando el orden relativo dentro de cada tipo: la
 * tercera parada del recorrido sigue yendo después de la segunda.
 */
export function sortStopsByKind(stops: TourStopDraft[]): TourStopDraft[] {
    return stops
        .map((stop, index) => ({ stop, index }))
        .sort(
            (a, b) =>
                KIND_ORDER[a.stop.kind] - KIND_ORDER[b.stop.kind] ||
                a.index - b.index,
        )
        .map(({ stop }) => stop);
}

/** ¿Tiene la ruta un punto de recogida y uno de regreso? (checklist de publicación) */
export function hasEndpoints(stops: TourStopDraft[]): boolean {
    return (
        stops.some((stop) => stop.kind === 'pickup') &&
        stops.some((stop) => stop.kind === 'drop')
    );
}
