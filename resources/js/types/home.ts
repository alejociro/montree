export type HomePromotion = {
    id: number;
    name: string;
    description: string | null;
    discount_label: string;
    cover_image_url: string | null;
    tour: { slug: string; name: string } | null;
};

export type UpcomingDeparture = {
    id: number;
    starts_at: string;
    ends_at: string | null;
    available_seats: number;
    effective_price: string;
    tour: {
        name: string;
        slug: string;
        currency: string;
        cover_image_url: string | null;
    };
};

export type HomeTestimonial = {
    id: number;
    rating: number;
    title: string | null;
    body: string | null;
    author_name: string | null;
    tour: { name: string; slug: string } | null;
    created_at: string | null;
};
