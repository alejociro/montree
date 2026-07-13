export type TourDateStatus = 'open' | 'full' | 'closed' | 'cancelled';

export type TourDateScope = 'upcoming' | 'past' | 'all';

export interface LogisticsRef {
    id: number;
    name: string;
}

export interface TourDateAdmin {
    id: number;
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

export interface TourDateFormInput {
    starts_at: string;
    ends_at: string;
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
