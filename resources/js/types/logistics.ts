import type {
    AccommodationType,
    CancellationPolicy,
    HotelAmenity,
    MealPlan,
    PaymentTerms,
    ProviderDocumentType,
    ProviderServiceType,
    RateUnit,
    RouteKind,
    RouteSeason,
    TaxRegime,
    TourDateDisplayStatus,
    TourDateStatus,
    TourDifficulty,
    TourStopKind,
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

export interface RouteStopRecord {
    id: number;
    position: number;
    name: string;
    kind: TourStopKind;
    time_label: string | null;
}

export interface RouteResource {
    id: number;
    name: string;
    description: string | null;
    kind: RouteKind | null;
    difficulty: TourDifficulty | null;
    start_point: string | null;
    start_latitude: string | null;
    start_longitude: string | null;
    end_point: string | null;
    end_latitude: string | null;
    end_longitude: string | null;
    city: string | null;
    state: string | null;
    country: string | null;
    distance_km: string | null;
    duration_hours: string | null;
    max_altitude_m: number | null;
    elevation_gain_m: number | null;
    group_capacity: number | null;
    seasons: RouteSeason[];
    safety_notes: string | null;
    required_gear: string[];
    permits: string | null;
    emergency_contact: string | null;
    stops: RouteStopRecord[];
    tour_dates_count: number;
}

export interface ProviderRateRecord {
    id: number;
    position: number;
    concept: string;
    amount: string | null;
    unit: RateUnit;
}

export interface ProviderDocumentRecord {
    id: number;
    position: number;
    kind: ProviderDocumentType;
    number: string | null;
    expires_at: string | null;
}

export interface ProviderResource {
    id: number;
    name: string;
    service_type: ProviderServiceType | null;
    description: string | null;
    legal_name: string | null;
    tax_id: string | null;
    tax_regime: TaxRegime | null;
    billing_email: string | null;
    bank_account: string | null;
    payment_terms: PaymentTerms | null;
    contact_name: string | null;
    contact_role: string | null;
    contact_phone: string | null;
    contact_email: string | null;
    alternate_contact: string | null;
    service_hours: string | null;
    address: string | null;
    latitude: string | null;
    longitude: string | null;
    city: string | null;
    state: string | null;
    coverage: string | null;
    currency: string | null;
    rates_valid_until: string | null;
    notes: string | null;
    rates: ProviderRateRecord[];
    documents: ProviderDocumentRecord[];
    tour_dates_count: number;
}

export interface HotelRoomRecord {
    id: number;
    position: number;
    name: string;
    quantity: number | null;
    nightly_rate: string | null;
}

export interface HotelResource {
    id: number;
    name: string;
    accommodation_type: AccommodationType | null;
    star_rating: number | null;
    description: string | null;
    legal_name: string | null;
    tax_id: string | null;
    address: string | null;
    latitude: string | null;
    longitude: string | null;
    city: string | null;
    state: string | null;
    country: string | null;
    directions: string | null;
    total_capacity: number | null;
    currency: string | null;
    rates_valid_until: string | null;
    check_in: string | null;
    check_out: string | null;
    amenities: HotelAmenity[];
    meal_plan: MealPlan | null;
    diets: string | null;
    restrictions: string | null;
    contact_name: string | null;
    contact_phone: string | null;
    contact_email: string | null;
    emergency_contact: string | null;
    cancellation_policy: CancellationPolicy | null;
    payment_terms: PaymentTerms | null;
    notes: string | null;
    rooms: HotelRoomRecord[];
    tour_dates_count: number;
}

export type LogisticsResourceKind = 'routes' | 'providers' | 'hotels';

/** Cualquiera de las tres fichas, tal como vuelve del listado. */
export type LogisticsRecord = RouteResource | ProviderResource | HotelResource;

/**
 * Valor de un campo del formulario de ficha. Las listas repetibles guardan sus
 * filas como objetos planos; el resto es texto, selección múltiple o nada.
 */
export type LogisticsFieldValue =
    | string
    | string[]
    | Record<string, string>[]
    | null;

export type LogisticsFormState = Record<string, LogisticsFieldValue>;

/** Un dato de la lista de la derecha en la ficha de la rejilla. */
export interface LogisticsFact {
    label: string;
    value: string;
}

export interface LogisticsPaginatedResponse<TResource> {
    data: TResource[];
    meta: PaginationMeta;
}

export interface TourDateListResponse {
    data: TourDateAdmin[];
}
