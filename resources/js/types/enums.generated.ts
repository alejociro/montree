/**
 * ARCHIVO GENERADO — no lo edites a mano.
 *
 * Espejo de los enums de `app/Enums` con respaldo de string: mismos
 * valores y mismo orden. Se regenera con `php artisan enums:typescript`
 * y la suite falla si queda desactualizado.
 */

/** `App\Enums\AccommodationType` */
export const ACCOMMODATION_TYPE_VALUES = [
    'ecolodge',
    'hotel',
    'rural_inn',
    'hostel',
    'glamping',
    'farm',
] as const;

export type AccommodationType = (typeof ACCOMMODATION_TYPE_VALUES)[number];

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

/** `App\Enums\CancellationPolicy` */
export const CANCELLATION_POLICY_VALUES = [
    'free_48_hours',
    'free_7_days',
    'non_refundable',
    'per_contract',
] as const;

export type CancellationPolicy = (typeof CANCELLATION_POLICY_VALUES)[number];

/** `App\Enums\DepartureScope` */
export const DEPARTURE_SCOPE_VALUES = [
    'upcoming',
    'today',
    'past',
    'disabled',
    'all',
] as const;

export type DepartureScope = (typeof DEPARTURE_SCOPE_VALUES)[number];

/** `App\Enums\DocumentType` */
export const DOCUMENT_TYPE_VALUES = [
    'cc',
    'ce',
    'ti',
    'sisben',
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

/** `App\Enums\HotelAmenity` */
export const HOTEL_AMENITY_VALUES = [
    'breakfast',
    'lunch',
    'dinner',
    'wifi',
    'hot_water',
    'parking',
    'pool',
    'bonfire',
    'laundry',
    'wheelchair_access',
] as const;

export type HotelAmenity = (typeof HOTEL_AMENITY_VALUES)[number];

/** `App\Enums\MealPlan` */
export const MEAL_PLAN_VALUES = [
    'breakfast_only',
    'half_board',
    'full_board',
    'none',
] as const;

export type MealPlan = (typeof MEAL_PLAN_VALUES)[number];

/** `App\Enums\NewsletterSubscriberStatus` */
export const NEWSLETTER_SUBSCRIBER_STATUS_VALUES = [
    'active',
    'unsubscribed',
    'bounced',
] as const;

export type NewsletterSubscriberStatus = (typeof NEWSLETTER_SUBSCRIBER_STATUS_VALUES)[number];

/** `App\Enums\PaymentGateway` */
export const PAYMENT_GATEWAY_VALUES = [
    'placetopay',
    'cash',
    'transfer',
] as const;

export type PaymentGateway = (typeof PAYMENT_GATEWAY_VALUES)[number];

/** `App\Enums\PaymentStatus` */
export const PAYMENT_STATUS_VALUES = [
    'pending',
    'processing',
    'completed',
    'failed',
    'refunded',
] as const;

export type PaymentStatus = (typeof PAYMENT_STATUS_VALUES)[number];

/** `App\Enums\PaymentTerms` */
export const PAYMENT_TERMS_VALUES = [
    'advance_30',
    'advance_50',
    'prepaid',
    'credit_15',
    'credit_30',
] as const;

export type PaymentTerms = (typeof PAYMENT_TERMS_VALUES)[number];

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

/** `App\Enums\ProviderDocumentType` */
export const PROVIDER_DOCUMENT_TYPE_VALUES = [
    'liability_policy',
    'tax_registry',
    'chamber_of_commerce',
    'health_registry',
    'operation_card',
    'other',
] as const;

export type ProviderDocumentType = (typeof PROVIDER_DOCUMENT_TYPE_VALUES)[number];

/** `App\Enums\ProviderServiceType` */
export const PROVIDER_SERVICE_TYPE_VALUES = [
    'transport',
    'food',
    'guiding',
    'activities',
    'equipment',
    'other',
] as const;

export type ProviderServiceType = (typeof PROVIDER_SERVICE_TYPE_VALUES)[number];

/** `App\Enums\RateUnit` */
export const RATE_UNIT_VALUES = [
    'per_day',
    'per_person',
    'per_service',
    'per_hour',
] as const;

export type RateUnit = (typeof RATE_UNIT_VALUES)[number];

/** `App\Enums\ReviewStatus` */
export const REVIEW_STATUS_VALUES = [
    'pending',
    'approved',
    'rejected',
] as const;

export type ReviewStatus = (typeof REVIEW_STATUS_VALUES)[number];

/** `App\Enums\RouteKind` */
export const ROUTE_KIND_VALUES = [
    'hiking',
    'land',
    'mixed',
    'water',
    'cycling',
] as const;

export type RouteKind = (typeof ROUTE_KIND_VALUES)[number];

/** `App\Enums\RouteSeason` */
export const ROUTE_SEASON_VALUES = [
    'all_year',
    'december_february',
    'june_august',
    'avoid_rain',
] as const;

export type RouteSeason = (typeof ROUTE_SEASON_VALUES)[number];

/** `App\Enums\SubdomainAvailabilityReason` */
export const SUBDOMAIN_AVAILABILITY_REASON_VALUES = [
    'taken',
    'reserved',
    'invalid_format',
] as const;

export type SubdomainAvailabilityReason = (typeof SUBDOMAIN_AVAILABILITY_REASON_VALUES)[number];

/** `App\Enums\TaxRegime` */
export const TAX_REGIME_VALUES = [
    'vat_responsible',
    'vat_exempt',
    'simple_regime',
] as const;

export type TaxRegime = (typeof TAX_REGIME_VALUES)[number];

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

/** `App\Enums\TransactionSearchField` */
export const TRANSACTION_SEARCH_FIELD_VALUES = [
    'reference',
    'request_id',
    'internal_reference',
    'authorization',
    'receipt',
    'booking_number',
    'payer',
] as const;

export type TransactionSearchField = (typeof TRANSACTION_SEARCH_FIELD_VALUES)[number];

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
