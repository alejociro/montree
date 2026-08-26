import type {
    TourDateDisplayStatus,
    TourDateStatus,
} from '@/types/enums.generated';
import type { PaginationLinks, PaginationMeta } from './pagination';

// Los dos estados salen de `app/Enums` vía `php artisan enums:typescript`; se
// reexportan aquí porque medio frontend ya los importa desde este archivo.

export type { TourDateDisplayStatus, TourDateStatus };

export type TourDateScope = 'upcoming' | 'past' | 'all';

export interface TourRef {
    id: number;
    name: string;
    slug: string;
    currency: string;
}

export interface LogisticsRef {
    id: number;
    name: string;
}

export interface TourDateAdmin {
    id: number;
    /** `TD<tour>-<mmdd>`, derivado en el backend. No hay columna que lo guarde. */
    code: string;
    starts_at: string;
    ends_at: string | null;
    capacity: number;
    booked_count: number;
    available_seats: number;
    price_override: string | null;
    effective_price: string;
    status: TourDateStatus;
    notes: string | null;
    guide: LogisticsRef | null;
    route: LogisticsRef | null;
    provider: LogisticsRef | null;
    hotels: LogisticsRef[];
}

export interface TourDateGlobalAdmin extends TourDateAdmin {
    display_status: TourDateDisplayStatus;
    tour: TourRef;
}

export type TourDateGlobalStatusFilter = TourDateDisplayStatus | '';

export interface TourDatesGlobalFilters {
    status: TourDateGlobalStatusFilter;
    tour_id: number | null;
    from: string;
    to: string;
    direction: 'asc' | 'desc';
}

// Reexportados desde `types/pagination.ts`: el shape de paginador de Laravel no
// es propio de logistica, lo comparten todos los listados del panel.
export type { PaginationLinks, PaginationMeta };

/** Bandejas del tablero de salidas; espeja `App\Enums\DepartureScope`. */
export type DepartureScopeId =
    | 'upcoming'
    | 'today'
    | 'past'
    | 'disabled'
    | 'all';

/** KPIs de cabecera: describen la operación completa, no el filtro activo. */
export interface DepartureBoardStats {
    active: number;
    seats_left: number;
    travellers: number;
    without_guide: number;
}

/** Totales del pie: sí siguen al corte que el usuario está viendo. */
export interface DepartureBoardTotals {
    departures: number;
    travellers: number;
    seats_left: number;
}

export interface TourDatesGlobalResponse {
    data: TourDateGlobalAdmin[];
    links: PaginationLinks;
    meta: PaginationMeta;
    stats: DepartureBoardStats;
    counts: Record<DepartureScopeId, number>;
    totals: DepartureBoardTotals;
}

export interface TourDateFormInput {
    starts_at: string;
    capacity: number;
    price_override: string;
    notes: string;
    guide_id: number | null;
    route_id: number | null;
    provider_id: number | null;
    hotel_ids: number[];
}

export interface RouteResource {
    id: number;
    name: string;
    description: string | null;
    distance_km: string | null;
    duration_hours: string | null;
    tour_dates_count: number;
}

export interface ProviderResource {
    id: number;
    name: string;
    service_type: string | null;
    contact_name: string | null;
    contact_phone: string | null;
    contact_email: string | null;
    notes: string | null;
    tour_dates_count: number;
}

export interface HotelResource {
    id: number;
    name: string;
    address: string | null;
    contact_phone: string | null;
    contact_email: string | null;
    notes: string | null;
    tour_dates_count: number;
}

export type LogisticsResourceKind = 'routes' | 'providers' | 'hotels';

export interface LogisticsField {
    key: string;
    label: string;
    type: 'text' | 'number' | 'email' | 'textarea';
    placeholder?: string;
    required?: boolean;
    fullWidth?: boolean;
}

export interface LogisticsRow {
    id: number;
    name: string;
    tour_dates_count: number;
    [key: string]: string | number | null;
}

export interface LogisticsListResponse<TResource> {
    data: TResource[];
}

export interface TourDateListResponse {
    data: TourDateAdmin[];
}
