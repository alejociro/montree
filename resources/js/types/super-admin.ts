import type { CommissionType } from './enums.generated';

export type { CommissionType };
import type { PaginatedResponse } from './pagination';
import type { TenantConfiguration, TenantPlan, TenantStatus } from './tenant';

export type MonthPoint = {
    month: string;
    label: string;
    value: number | string;
};

export type TenantCommission = {
    type: CommissionType | null;
    value: string | null;
    currency: string;
};

export type TenantStats = {
    users_count: number;
    tours_count: number;
    bookings_count_30d: number;
    revenue_30d: string;
    charges_30d: string;
};

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
    created_at: string | null;
    can_enter: boolean;
    commission: TenantCommission;
    stats: TenantStats;
    configuration?: TenantConfiguration | null;
};

export type TenantsListFilters = {
    search: string | null;
    status: TenantStatus | null;
    plan: TenantPlan | null;
    sort: 'created_at' | 'name';
    direction: 'asc' | 'desc';
};

/**
 * Un importe agregado de plataforma. Va siempre con su moneda: los cargos se
 * guardan en la de cada agencia y no se convierten, así que dos monedas son
 * dos números, nunca una suma.
 */
export type CurrencyAmount = {
    currency: string;
    amount: string;
};

export type PlatformMetricsTotals = {
    tenants: number;
    active_tenants: number;
    users: number;
    bookings_this_month: number;
    revenue_this_month: CurrencyAmount[];
    earnings_this_month: CurrencyAmount[];
};

export type PlatformMetricsGrowth = {
    tenants_new_this_month: number;
    bookings_growth_pct: number;
};

export type PlatformCharts = {
    tenants_per_month: {
        points: MonthPoint[];
        average: number;
    };
    revenue_per_tenant: {
        months: string[];
        series: { tenant: string; currency: string; values: string[] }[];
    };
    earnings_per_month: {
        series: { currency: string; points: MonthPoint[]; total: string }[];
    };
};

export type PlatformChargeRow = {
    id: number;
    charged_at: string;
    booking: {
        id: number;
        booking_number: string;
        total_amount: string;
        currency: string;
    } | null;
    base_amount: string;
    type: CommissionType;
    applied_value: string;
    amount: string;
    currency: string;
};

export type PlatformChargeTotals = {
    amount: string;
    count: number;
    currency: string;
};

export type TenantChargesSummary = {
    total_amount: string;
    total_count: number;
    currency: string;
};

export type PlatformChargeFilters = {
    from: string | null;
    to: string | null;
};

export type TenantMonthlySeries = {
    bookings: MonthPoint[];
    charges: MonthPoint[];
};

export type TenantsListPaginated = PaginatedResponse<SuperAdminTenantSummary>;

export type PlatformChargesPaginated = PaginatedResponse<PlatformChargeRow>;
