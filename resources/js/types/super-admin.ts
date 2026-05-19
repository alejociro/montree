import type { TenantConfiguration, TenantPlan, TenantStatus } from './tenant';

export type SuperAdminTenantSummary = {
    id: number;
    slug: string;
    name: string;
    domain: string | null;
    status: TenantStatus;
    plan: TenantPlan;
    trial_ends_at: string | null;
    suspended_at: string | null;
    contact_email: string | null;
    contact_phone: string | null;
    users_count: number | null;
    tours_count: number | null;
    bookings_count_30d: number | null;
    revenue_30d: string | null;
    created_at: string | null;
    configuration?: TenantConfiguration | null;
};

export type PlatformMetricsTotals = {
    tenants: number;
    active_tenants: number;
    users: number;
    bookings_this_month: number;
    revenue_this_month: string;
    platform_commission_this_month: string;
};

export type PlatformMetricsGrowth = {
    tenants_new_this_month: number;
    bookings_growth_pct: number;
};

export type PlatformMetrics = {
    totals: PlatformMetricsTotals;
    growth: PlatformMetricsGrowth;
    plan_distribution: Record<TenantPlan, number>;
};

export type TenantsListPaginated = {
    data: SuperAdminTenantSummary[];
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        per_page: number;
        to: number | null;
        total: number;
    };
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
};

export type TenantsListFilters = {
    search: string;
    status: TenantStatus | '';
    plan: TenantPlan | '';
    sort: 'created_at' | 'name';
    direction: 'asc' | 'desc';
    per_page: number;
};
