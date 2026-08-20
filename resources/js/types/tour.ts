export type TourStatus = 'draft' | 'active' | 'paused' | 'archived';
export type TourDifficulty = 'easy' | 'moderate' | 'hard' | 'extreme';

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
    created_at: string;
    updated_at: string;
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

export const TOUR_STATUSES: TourStatus[] = [
    'draft',
    'active',
    'paused',
    'archived',
];
export const TOUR_DIFFICULTIES: TourDifficulty[] = [
    'easy',
    'moderate',
    'hard',
    'extreme',
];
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
