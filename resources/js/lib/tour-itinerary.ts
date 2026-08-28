import type { TourItineraryDraft, TourStopDraft } from '@/types/tour';

/**
 * Utilidades del constructor «Itinerario y paradas».
 *
 * Regla de negocio (handoff 09): **una parada pertenece a un paso del
 * itinerario**, y un paso puede tener varias. El enlace vive en
 * `stop.itinerary_step`, que guarda el `step_number` del paso — no su índice—,
 * porque el número es lo que ve el usuario y lo que viaja al backend.
 */
export type StopEntry = {
    stop: TourStopDraft;
    /** Índice en `stops[]`: es el identificador del punto en el mapa. */
    index: number;
};

export type StepGroup = {
    step: TourItineraryDraft;
    index: number;
    stops: StopEntry[];
};

export function stepKeyOf(step: TourItineraryDraft): string {
    return String(step.step_number);
}

export function groupStopsBySteps(
    steps: TourItineraryDraft[],
    stops: TourStopDraft[],
): StepGroup[] {
    return steps.map((step, index) => ({
        step,
        index,
        stops: stops
            .map((stop, stopIndex) => ({ stop, index: stopIndex }))
            .filter((entry) => entry.stop.itinerary_step === stepKeyOf(step)),
    }));
}

/**
 * Paradas que no cuelgan de ningún paso.
 *
 * WHY: al borrar un paso sus paradas quedarían huérfanas y desaparecerían de la
 * pantalla aunque sigan en el formulario —y en el mapa—. Se muestran aparte para
 * poder reasignarlas o eliminarlas a conciencia.
 */
export function orphanStops(
    steps: TourItineraryDraft[],
    stops: TourStopDraft[],
): StopEntry[] {
    const known = new Set(steps.map(stepKeyOf));

    return stops
        .map((stop, index) => ({ stop, index }))
        .filter(
            (entry) =>
                entry.stop.itinerary_step === '' ||
                !known.has(entry.stop.itinerary_step),
        );
}

/**
 * Clave de ubicación con la precisión de un pin arrastrado a mano (~1 m).
 * Dos paradas con la misma clave son el mismo lugar.
 */
export function placeKey(stop: {
    latitude: string;
    longitude: string;
}): string | null {
    const latitude = Number.parseFloat(stop.latitude);
    const longitude = Number.parseFloat(stop.longitude);

    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
        return null;
    }

    return `${latitude.toFixed(5)},${longitude.toFixed(5)}`;
}

export type SharedPlace = {
    key: string;
    /** Índices en `stops[]` que ocurren en este lugar, en orden. */
    indexes: number[];
};

export function sharedPlaces(stops: TourStopDraft[]): SharedPlace[] {
    const groups = new Map<string, number[]>();

    stops.forEach((stop, index) => {
        const key = placeKey(stop);

        if (key === null) {
            return;
        }

        groups.set(key, [...(groups.get(key) ?? []), index]);
    });

    return [...groups.entries()].map(([key, indexes]) => ({ key, indexes }));
}

export type SavedPlace = {
    key: string;
    name: string;
    address: string;
    latitude: string;
    longitude: string;
    /** Números de parada (1-based) que ya lo usan. */
    usedBy: number[];
};

/**
 * Lugares ya guardados en el tour, para reutilizarlos en otra parada sin
 * volver a buscarlos. Uno por ubicación, no uno por parada.
 */
export function savedPlacesOf(stops: TourStopDraft[]): SavedPlace[] {
    const places = new Map<string, SavedPlace>();

    stops.forEach((stop, index) => {
        const key = placeKey(stop);

        if (key === null) {
            return;
        }

        const existing = places.get(key);

        if (existing !== undefined) {
            existing.usedBy.push(index + 1);

            return;
        }

        places.set(key, {
            key,
            name: stop.name.trim() === '' ? stop.place : stop.name,
            address: stop.place,
            latitude: stop.latitude,
            longitude: stop.longitude,
            usedBy: [index + 1],
        });
    });

    return [...places.values()];
}
