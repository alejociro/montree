import type { PaymentGateway } from '@/types/enums.generated';

export type DashboardPeriodKey =
    | 'last_7_days'
    | 'last_30_days'
    | 'last_90_days'
    | 'this_month'
    | 'last_month'
    | 'this_year';

export type DashboardPeriod = {
    key: DashboardPeriodKey;
    start: string;
    end: string;
    previous_start: string;
    previous_end: string;
};

export type DashboardRevenuePoint = {
    date: string;
    amount: string;
};

/**
 * Una fila del desglose de ingresos por medio de pago. El backend manda
 * **siempre los tres medios**, en el orden del enum y aunque estén en cero: un
 * medio ausente sería indistinguible de un medio sin movimientos.
 */
export type RevenueByMethodRow = {
    method: PaymentGateway;
    label: string;
    amount: string;
    /** Entero redondeado sobre el bruto. Con bruto `0.00` todos vienen en `0`. */
    share_pct: number;
};

export type DashboardRevenue = {
    gross: string;
    net: string;
    currency: string;
    growth_pct: number | null;
    previous_gross: string;
    series: DashboardRevenuePoint[];
    by_method: RevenueByMethodRow[];
};

export type DashboardBookings = {
    total: number;
    confirmed: number;
    pending_payment: number;
    cancelled: number;
    growth_pct: number | null;
    previous_total: number;
};

export type DashboardRating = {
    average: string;
    count: number;
};

export type DashboardOccupancy = {
    upcoming_dates_count: number;
    total_capacity: number;
    booked_seats: number;
    occupancy_pct: number | null;
};

export type DashboardTopTour = {
    id: number;
    slug: string;
    name: string;
    bookings_count: number;
    revenue: string;
    rating_average: string;
    cover_image_url: string | null;
};

export type DashboardUpcomingDate = {
    id: number;
    tour_id: number;
    tour_name: string | null;
    starts_at: string;
    capacity_total: number;
    capacity_booked: number;
    occupancy_pct: number | null;
    guide_name: string | null;
};

export type DashboardPermissions = {
    can_export_reports: boolean;
};

export type DashboardSnapshot = {
    period: DashboardPeriod;
    revenue: DashboardRevenue;
    bookings: DashboardBookings;
    rating: DashboardRating;
    occupancy: DashboardOccupancy;
    top_tours: DashboardTopTour[];
    upcoming_dates: DashboardUpcomingDate[];
    pending_reviews_count: number;
    permissions: DashboardPermissions;
};

export type DashboardPeriodOption = {
    value: DashboardPeriodKey;
    label: string;
};

export type DashboardFilters = {
    period: DashboardPeriodKey;
};

/** Props de la page `Admin/Dashboard` (`DashboardPagesController::__invoke`). */
export type DashboardPageProps = {
    snapshot: DashboardSnapshot;
    periods: DashboardPeriodOption[];
    filters: DashboardFilters;
};
