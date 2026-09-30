export type PlatformLegalEntity = {
    name: string | null;
    nit: string | null;
    address: string | null;
    city: string | null;
    phone: string | null;
    email: string;
    privacy_email: string;
};

export type CommissionTier = {
    from: string;
    to: string | null;
    rate: string;
};

export type CommissionSchedule = {
    currency: string;
    tiers: CommissionTier[];
    max_charge: string | null;
};

export type PlatformInfo = {
    legal: PlatformLegalEntity;
    commissionSchedule: CommissionSchedule | null;
};
