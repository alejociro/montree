export type TourDetailImage = {
    id: number;
    url: string | null;
    is_cover: boolean;
    alt_text: string | null;
    display_order: number;
};

export type TourDetailItineraryStep = {
    step_number: number;
    title: string;
    description: string | null;
    duration_label: string | null;
};

export type TourDetailDate = {
    id: number;
    starts_at: string;
    ends_at: string | null;
    price_override: string | null;
    effective_price: string;
    capacity_total: number;
    capacity_booked: number;
    available_seats: number;
    is_full: boolean;
    status: 'open' | 'full' | 'cancelled' | 'closed';
};

export type TourDetail = {
    id: number;
    slug: string;
    name: string;
    short_description: string | null;
    description: string;
    base_price: string;
    currency: string;
    duration_hours: number;
    difficulty: 'easy' | 'moderate' | 'hard' | 'expert';
    default_capacity: number;
    category: { id: number; name: string; slug: string } | null;
    rating_average: string;
    rating_count: number;
    rating_distribution: Record<'1' | '2' | '3' | '4' | '5', number>;
    images: TourDetailImage[];
    cover_image_url: string | null;
    itinerary: TourDetailItineraryStep[];
    requirements: string[];
    includes: string[];
    meeting_point: string | null;
    meeting_latitude: string | null;
    meeting_longitude: string | null;
    future_dates: TourDetailDate[];
    is_favorite: boolean;
};

export type ReviewSummary = {
    id: number;
    rating: number;
    title: string | null;
    body: string | null;
    author_name: string | null;
    created_at: string | null;
    admin_response: string | null;
    admin_responded_at: string | null;
};
