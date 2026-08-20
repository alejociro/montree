import type {
    TourStop,
    TourStopDraft,
    TourStopKind,
    TourStopPayload,
} from '@/types/tour';

export const TOUR_STOP_KINDS: TourStopKind[] = ['pickup', 'site', 'drop'];

export function emptyTourStopDraft(kind: TourStopKind = 'site'): TourStopDraft {
    return {
        kind,
        label: '',
        name: '',
        place: '',
        time: '',
        latitude: '',
        longitude: '',
        itinerary_step: '',
    };
}

export function tourStopDraftsFrom(stops: TourStop[]): TourStopDraft[] {
    return stops.map((stop) => ({
        kind: stop.kind,
        label: stop.label ?? '',
        name: stop.name,
        place: stop.place ?? '',
        time: stop.time ?? '',
        latitude: String(stop.latitude),
        longitude: String(stop.longitude),
        itinerary_step:
            stop.itinerary_step === null ? '' : String(stop.itinerary_step),
    }));
}

/**
 * WHY: una coordenada vacía se manda como `null`, no como 0: (0, 0) es un punto
 * válido en medio del Atlántico y pasaría la validación sin que nadie lo note.
 */
function coordinate(value: string): number | null {
    const parsed = Number.parseFloat(value);

    return Number.isFinite(parsed) ? parsed : null;
}

function textOrNull(value: string): string | null {
    const trimmed = value.trim();

    return trimmed === '' ? null : trimmed;
}

export function tourStopsPayload(drafts: TourStopDraft[]): TourStopPayload[] {
    return drafts.map((draft) => ({
        kind: draft.kind,
        label: textOrNull(draft.label),
        name: draft.name.trim(),
        place: textOrNull(draft.place),
        time: textOrNull(draft.time),
        latitude: coordinate(draft.latitude),
        longitude: coordinate(draft.longitude),
        itinerary_step:
            draft.itinerary_step === ''
                ? null
                : Number(draft.itinerary_step) || null,
    }));
}
