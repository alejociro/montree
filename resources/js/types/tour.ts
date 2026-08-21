import {
    TOUR_DIFFICULTY_VALUES,
    TOUR_STATUS_VALUES,
} from '@/types/enums.generated';
import type { TourDifficulty, TourStatus } from '@/types/enums.generated';

// WHY: los valores salen de `enums.generated.ts` (`php artisan enums:typescript`),
// no de un espejo escrito a mano que se desincroniza en silencio.
export type { TourDifficulty, TourStatus };

export type TourCategory = {
    id: number;
    name: string;
    slug: string;
    icon: string | null;
};

export type TourImage = {
    id: number;
    tour_id: number;
    url: string;
    alt_text: string | null;
    display_order: number;
    is_cover: boolean;
};

export type TourStopKind = 'pickup' | 'site' | 'drop';

export type TourStop = {
    kind: TourStopKind;
    code: string;
    label: string | null;
    name: string;
    place: string | null;
    time: string | null;
    latitude: number;
    longitude: number;
    itinerary_step: number | null;
};

export type TourItineraryStep = {
    id: number;
    step_number: number;
    title: string;
    description: string;
    duration_label: string | null;
};

export type TourSummary = {
    id: number;
    slug: string;
    name: string;
    short_description: string | null;
    status: TourStatus;
    base_price: string;
    currency: string;
    duration_hours: number;
    difficulty: TourDifficulty;
    default_capacity: number;
    category: TourCategory | null;
    cover_image_url: string | null;
    images_count?: number;
    bookings_count?: number;
    rating_average: string;
    rating_count: number;
    operations?: TourOperationalSummary;
    created_at: string;
    updated_at: string;
};

export type Tour = {
    id: number;
    slug: string;
    name: string;
    short_description: string | null;
    description: string;
    status: TourStatus;
    category_id: number | null;
    category: TourCategory | null;
    default_guide_id: number | null;
    base_price: string;
    currency: string;
    duration_hours: number;
    difficulty: TourDifficulty;
    default_capacity: number;
    meeting_point: string | null;
    meeting_latitude: string | null;
    meeting_longitude: string | null;
    includes: string[];
    excludes: string[];
    requirements: string[];
    rating_average: string;
    rating_count: number;
    images: TourImage[];
    itinerary: TourItineraryStep[];
    stops: TourStop[];
    /**
     * Checklist «Para publicar» calculado por el servidor, que es quien rechaza
     * la activación. La pantalla puede recalcular `done` sobre lo que hay en el
     * formulario sin guardar, pero quién bloquea sale de aquí.
     */
    publish_checklist: TourPublishRequirement[];
    /** A cuánta gente se le avisaría si se mueve la parada de recogida (regla 6). */
    pickup_change_impact: TourPickupChangeImpact;
    created_at: string;
    updated_at: string;
};

/**
 * Reservas vivas con salida por venir: lo que se notifica por correo al cambiar
 * la parada de recogida. El mismo criterio con el que después se envía.
 */
export type TourPickupChangeImpact = {
    bookings: number;
    passengers: number;
};

export type TourItineraryDraft = {
    step_number: number;
    title: string;
    description: string;
    duration_label: string;
};

/**
 * Parada tal como la edita el formulario: todo texto, porque los inputs no
 * pueden sostener un número a medio escribir. `tourStopsPayload` la convierte.
 */
export type TourStopDraft = {
    kind: TourStopKind;
    label: string;
    name: string;
    place: string;
    time: string;
    latitude: string;
    longitude: string;
    itinerary_step: string;
};

export type TourStopPayload = {
    kind: TourStopKind;
    label: string | null;
    name: string;
    place: string | null;
    time: string | null;
    latitude: number | null;
    longitude: number | null;
    itinerary_step: number | null;
};

export type TourFormPayload = {
    name: string;
    default_guide_id: number | null;
    short_description: string;
    description: string;
    category_id: number | null;
    base_price: string;
    currency: string;
    duration_hours: number;
    difficulty: TourDifficulty;
    default_capacity: number;
    meeting_point: string;
    meeting_latitude: string;
    meeting_longitude: string;
    includes: string[];
    excludes: string[];
    requirements: string[];
    itinerary: TourItineraryDraft[];
    stops: TourStopDraft[];
};

/**
 * Lo que viaja al backend al guardar. Los campos opcionales de texto se mandan
 * como `null` cuando quedan vacios (el formulario los mantiene como '' para
 * poder bindearlos a un input).
 */
export type TourSubmitPayload = Omit<
    TourFormPayload,
    | 'short_description'
    | 'meeting_point'
    | 'meeting_latitude'
    | 'meeting_longitude'
    | 'stops'
> & {
    short_description: string | null;
    meeting_point: string | null;
    meeting_latitude: string | null;
    meeting_longitude: string | null;
    stops: TourStopPayload[];
};

export type TourShowStats = {
    bookings: {
        total: number;
        confirmed: number;
        pending_payment: number;
        cancelled: number;
    };
    travelers_total: number;
    revenue_total: string;
    currency: string;
    occupancy_upcoming: {
        booked_total: number;
        capacity_total: number;
        rate: number;
    };
    upcoming_dates_count: number;
    next_date_starts_at: string | null;
};

/**
 * Cifras operativas por tour del listado del panel: próxima salida, pasajeros y
 * saldo. Opcional a propósito — `TourSummaryResource` solo las emite cuando la
 * consulta las adjunta (el listado del panel sí, el selector de promociones no);
 * la tarjeta oculta el bloque cuando no llegan.
 */
export type TourOperationalSummary = {
    next_departure_at: string | null;
    passengers_count: number;
    occupancy: {
        occupied: number;
        capacity: number;
    };
    passengers_with_due: number;
};

/** KPIs del encabezado del listado de tours del panel. */
export type TourIndexStats = {
    tours: {
        active: number;
        draft: number;
        paused: number;
        archived: number;
    };
    upcoming_departures: {
        count: number;
        next_starts_at: string | null;
    };
    occupancy: {
        booked_seats: number;
        total_capacity: number;
        rate: number;
    };
    /**
     * Dinero de pasajeros: el backend lo omite para quien no tiene
     * `bookings.view` (un operador, por ejemplo). Ausente, no en cero.
     */
    pending_balance?: {
        passengers: number;
        amount: string;
        currency: string;
    };
};

/** Estado de la barra de filtros del listado de tours del panel. */
export type TourIndexFilters = {
    status: TourStatus | 'all';
    category_id: number | null;
    search: string;
    sort: TourSortValue;
};

export type TourSortValue =
    | 'recent'
    | 'next_departure'
    | 'occupancy'
    | 'revenue'
    | 'name'
    | 'price_desc'
    | 'price_asc';

/**
 * Traducción del orden de la interfaz a los parámetros que acepta
 * `Api\V1\Admin\TourController@index`: columnas de `SORTABLE_COLUMNS` o
 * expresiones de `TourOperationalSummaryQuery::sortableExpressions()`.
 */
export const TOUR_SORT_PARAMS: Record<
    TourSortValue,
    { sort: string; direction: 'asc' | 'desc' }
> = {
    recent: { sort: 'created_at', direction: 'desc' },
    // WHY: la próxima salida se lee de más cercana a más lejana, así que va
    // `asc`; ocupación e ingresos se leen de mayor a menor.
    next_departure: { sort: 'next_departure', direction: 'asc' },
    occupancy: { sort: 'occupancy', direction: 'desc' },
    revenue: { sort: 'revenue', direction: 'desc' },
    name: { sort: 'name', direction: 'asc' },
    price_desc: { sort: 'base_price', direction: 'desc' },
    price_asc: { sort: 'base_price', direction: 'asc' },
};

export type PaginatedTours = {
    data: TourSummary[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        to: number | null;
        total: number;
        per_page: number;
        last_page: number;
    };
};

export const TOUR_STATUSES: TourStatus[] = [...TOUR_STATUS_VALUES];
export const TOUR_DIFFICULTIES: TourDifficulty[] = [...TOUR_DIFFICULTY_VALUES];
export const SUPPORTED_CURRENCIES = [
    'USD',
    'COP',
    'EUR',
    'MXN',
    'ARS',
    'PEN',
    'CLP',
    'BRL',
] as const;

export type SupportedCurrency = (typeof SUPPORTED_CURRENCIES)[number];

/** Bloques del formulario del tour, en el orden en que se editan. */
export const TOUR_FORM_STEP_IDS = [
    'general',
    'pricing',
    'detail',
    'route',
    'gallery',
] as const;

export type TourFormStepId = (typeof TOUR_FORM_STEP_IDS)[number];

/** Un bloque del formulario tal como lo pinta el riel de progreso. */
export type TourFormStep = {
    id: TourFormStepId;
    /** `id` del `<section>` al que salta el riel. */
    anchor: string;
    label: string;
    hint: string;
    done: boolean;
};

/**
 * Una condición del checklist de publicación. `blocking` distingue lo que el
 * backend rechaza (Form Request + `ChangeTourStatusAction`) de lo que solo se
 * recomienda: el checklist avisa, no inventa reglas (D7).
 */
export type TourPublishRequirement = {
    id: string;
    label: string;
    done: boolean;
    blocking: boolean;
    hint?: string;
};
