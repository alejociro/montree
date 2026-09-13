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
    placetopay: TenantCheckoutCredentials;
};

export type TenantCheckoutCredentials = {
    login: string | null;
    url: string | null;
    /** El tranKey nunca sale del servidor; solo se informa si hay uno guardado. */
    tran_key_set: boolean;
};

/**
 * Los términos llegan como prop de la página que los edita, no dentro de
 * `TenantConfiguration`: esa viaja compartida en toda respuesta Inertia y el
 * cuerpo crudo admite 20.000 caracteres.
 */
export type TenantTerms = {
    body: string | null;
    is_default: boolean;
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
    placetopay_login?: string | null;
    placetopay_tran_key?: string | null;
    placetopay_url?: string | null;
    terms_body?: string | null;
};
