export type PlatformLegalEntity = {
    name: string | null;
    nit: string | null;
    address: string | null;
    city: string | null;
    phone: string | null;
    email: string;
    privacy_email: string;
};

export type PlatformInfo = {
    legal: PlatformLegalEntity;
    trialDays: number;
};
