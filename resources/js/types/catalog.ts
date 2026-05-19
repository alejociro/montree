import type { TourDifficulty } from '@/types/tour';

export type CatalogCategory = {
    id: number;
    slug: string;
    name: string;
    icon: string | null;
    tours_count: number;
};

export type CatalogTour = {
    id: number;
    slug: string;
    name: string;
    short_description: string | null;
    base_price: string;
    currency: string;
    duration_hours: number;
    difficulty: TourDifficulty;
    default_capacity: number;
    category: {
        id: number;
        name: string;
        slug: string;
        icon: string | null;
    } | null;
    cover_image_url: string | null;
    rating_average: string;
    rating_count: number;
    next_date_starts_at: string | null;
    has_future_dates: boolean;
    is_favorite: boolean;
};

export type CatalogSort =
    | 'price_asc'
    | 'price_desc'
    | 'rating_desc'
    | 'newest'
    | 'next_date_asc';

export type CatalogFilters = {
    search: string | null;
    category: string | null;
    difficulty: TourDifficulty | null;
    price_min: number | null;
    price_max: number | null;
    sort: CatalogSort | null;
    per_page: number | null;
};

export type CatalogPaginatedTours = {
    data: CatalogTour[];
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

export const CATALOG_SORTS: CatalogSort[] = [
    'next_date_asc',
    'price_asc',
    'price_desc',
    'rating_desc',
    'newest',
];

export const CATALOG_SORT_LABELS: Record<CatalogSort, string> = {
    next_date_asc: 'Próxima fecha',
    price_asc: 'Precio: menor a mayor',
    price_desc: 'Precio: mayor a menor',
    rating_desc: 'Mejor valorados',
    newest: 'Más recientes',
};
