export type TenantStatus = 'active' | 'suspended' | 'pending';
export type TenantPlan = 'basic' | 'professional' | 'enterprise';
export type TenantLocale = 'es' | 'en';

export type Tenant = {
    id: number;
    slug: string;
    name: string;
    domain: string;
    status: TenantStatus;
    plan: TenantPlan;
    contact_email: string | null;
    contact_phone: string | null;
};

export type TenantSocialLinks = {
    instagram?: string;
    facebook?: string;
    twitter?: string;
    youtube?: string;
    tiktok?: string;
};

export type TenantContactInfo = {
    email?: string;
    phone?: string;
    address?: string;
    [key: string]: string | undefined;
};

export type TenantConfiguration = {
    primary_color: string | null;
    primary_color_hsl: string | null;
    secondary_color: string | null;
    secondary_color_hsl: string | null;
    logo_url: string | null;
    favicon_url: string | null;
    currency: string | null;
    timezone: string | null;
    locale: TenantLocale | null;
    tagline: string | null;
    description: string | null;
    social_links: TenantSocialLinks | null;
    contact_info: TenantContactInfo | null;
    reviews_require_moderation: boolean;
    require_traveler_details: boolean;
    custom_css: string | null;
    hero_image_url: string | null;
    min_partial_payment_pct: number;
};

export type TenantConfigurationPayload = {
    primary_color?: string | null;
    secondary_color?: string | null;
    currency?: string | null;
    timezone?: string | null;
    locale?: TenantLocale | null;
    tagline?: string | null;
    description?: string | null;
    social_links?: TenantSocialLinks | null;
    contact_info?: TenantContactInfo | null;
    reviews_require_moderation?: boolean;
    require_traveler_details?: boolean;
    custom_css?: string | null;
};
