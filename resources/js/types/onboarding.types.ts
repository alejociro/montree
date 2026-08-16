export type SubdomainAvailabilityReason =
    | null
    | 'taken'
    | 'reserved'
    | 'invalid_format';

export type SubdomainAvailability = {
    slug: string;
    available: boolean;
    reason: SubdomainAvailabilityReason;
};

export type SubdomainStatus = 'idle' | 'checking' | 'available' | 'unavailable';

export type AgencyRegistrationPayload = {
    agency_name: string;
    subdomain: string;
    founder_name: string;
    email: string;
    password: string;
    password_confirmation: string;
};
