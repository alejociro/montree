/**
 * ARCHIVO GENERADO — no lo edites a mano.
 *
 * Espejo de los enums de `app/Enums` con respaldo de string: mismos
 * valores y mismo orden. Se regenera con `php artisan enums:typescript`
 * y la suite falla si queda desactualizado.
 */

/** `App\Enums\BookingStatus` */
export const BOOKING_STATUS_VALUES = [
    'pending_payment',
    'confirmed',
    'cancelled',
    'completed',
    'refunded',
    'expired',
] as const;

export type BookingStatus = (typeof BOOKING_STATUS_VALUES)[number];

/** `App\Enums\DocumentType` */
export const DOCUMENT_TYPE_VALUES = [
    'cc',
    'ce',
    'ti',
    'passport',
    'other',
] as const;

export type DocumentType = (typeof DOCUMENT_TYPE_VALUES)[number];

/** `App\Enums\Eps` */
export const EPS_VALUES = [
    'sura',
    'nueva_eps',
    'sanitas',
    'salud_total',
    'other',
] as const;

export type Eps = (typeof EPS_VALUES)[number];

/** `App\Enums\NewsletterSubscriberStatus` */
export const NEWSLETTER_SUBSCRIBER_STATUS_VALUES = [
    'active',
    'unsubscribed',
    'bounced',
] as const;

export type NewsletterSubscriberStatus = (typeof NEWSLETTER_SUBSCRIBER_STATUS_VALUES)[number];

/** `App\Enums\PaymentGateway` */
export const PAYMENT_GATEWAY_VALUES = [
    'stripe',
    'manual',
] as const;

export type PaymentGateway = (typeof PAYMENT_GATEWAY_VALUES)[number];

/** `App\Enums\PaymentStatus` */
export const PAYMENT_STATUS_VALUES = [
    'pending',
    'processing',
    'completed',
    'failed',
    'refunded',
    'partially_refunded',
] as const;

export type PaymentStatus = (typeof PAYMENT_STATUS_VALUES)[number];

/** `App\Enums\PaymentType` */
export const PAYMENT_TYPE_VALUES = [
    'full',
    'partial',
    'remainder',
] as const;

export type PaymentType = (typeof PAYMENT_TYPE_VALUES)[number];

/** `App\Enums\PromotionType` */
export const PROMOTION_TYPE_VALUES = [
    'percentage',
    'fixed',
] as const;

export type PromotionType = (typeof PROMOTION_TYPE_VALUES)[number];

/** `App\Enums\ReviewStatus` */
export const REVIEW_STATUS_VALUES = [
    'pending',
    'approved',
    'rejected',
] as const;

export type ReviewStatus = (typeof REVIEW_STATUS_VALUES)[number];

/** `App\Enums\SubdomainAvailabilityReason` */
export const SUBDOMAIN_AVAILABILITY_REASON_VALUES = [
    'taken',
    'reserved',
    'invalid_format',
] as const;

export type SubdomainAvailabilityReason = (typeof SUBDOMAIN_AVAILABILITY_REASON_VALUES)[number];

/** `App\Enums\TenantMembershipStatus` */
export const TENANT_MEMBERSHIP_STATUS_VALUES = [
    'active',
    'invited',
    'suspended',
] as const;

export type TenantMembershipStatus = (typeof TENANT_MEMBERSHIP_STATUS_VALUES)[number];

/** `App\Enums\TenantPlan` */
export const TENANT_PLAN_VALUES = [
    'basic',
    'professional',
    'enterprise',
] as const;

export type TenantPlan = (typeof TENANT_PLAN_VALUES)[number];

/** `App\Enums\TenantStatus` */
export const TENANT_STATUS_VALUES = [
    'active',
    'pending',
    'suspended',
] as const;

export type TenantStatus = (typeof TENANT_STATUS_VALUES)[number];

/** `App\Enums\TourDateDisplayStatus` */
export const TOUR_DATE_DISPLAY_STATUS_VALUES = [
    'open',
    'full',
    'closed',
    'cancelled',
    'in_progress',
    'finished',
] as const;

export type TourDateDisplayStatus = (typeof TOUR_DATE_DISPLAY_STATUS_VALUES)[number];

/** `App\Enums\TourDateStatus` */
export const TOUR_DATE_STATUS_VALUES = [
    'open',
    'full',
    'cancelled',
    'closed',
] as const;

export type TourDateStatus = (typeof TOUR_DATE_STATUS_VALUES)[number];

/** `App\Enums\TourDifficulty` */
export const TOUR_DIFFICULTY_VALUES = [
    'easy',
    'moderate',
    'hard',
    'extreme',
] as const;

export type TourDifficulty = (typeof TOUR_DIFFICULTY_VALUES)[number];

/** `App\Enums\TourStatus` */
export const TOUR_STATUS_VALUES = [
    'draft',
    'active',
    'paused',
    'archived',
] as const;

export type TourStatus = (typeof TOUR_STATUS_VALUES)[number];

/** `App\Enums\TourStopKind` */
export const TOUR_STOP_KIND_VALUES = [
    'pickup',
    'site',
    'drop',
] as const;

export type TourStopKind = (typeof TOUR_STOP_KIND_VALUES)[number];

/** `App\Enums\UserRole` */
export const USER_ROLE_VALUES = [
    'super_admin',
    'admin',
    'sales',
    'operator',
    'guide',
    'customer',
] as const;

export type UserRole = (typeof USER_ROLE_VALUES)[number];
