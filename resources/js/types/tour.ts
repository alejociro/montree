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
    created_at: string;
    updated_at: string;
};

export type TourItineraryDraft = {
    step_number: number;
    title: string;
    description: string;
    duration_label: string;
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
