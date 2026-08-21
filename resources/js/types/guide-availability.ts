import type { TourDateStatus } from '@/types/enums.generated';

/**
 * Espejo de `contracts.md` — `GET /api/v1/admin/guides/availability`. Los días
 * ocupados van como fechas `Y-m-d`: la ocupación del guía se mide en días
 * calendario completos, no en horas (D9).
 */
export interface BusyBlock {
    tour_date_id: number;
    tour_name: string;
    from: string;
    to: string;
    status: TourDateStatus;
}

export interface GuideAvailability {
    id: number;
    name: string;
    busy: BusyBlock[];
}

export interface GuideAvailabilityResponse {
    data: GuideAvailability[];
}

/** El rango que ocuparía la salida que se está editando. */
export interface DepartureRange {
    from: string;
    to: string;
}
