import type { DocumentType, Eps } from '@/types/booking';

/**
 * Espejo exacto de `contracts.md §0` (`App\Http\Resources\Passenger\*`).
 *
 * Dos cosas que este archivo tiene que dejar dichas en el tipo, no en un
 * comentario suelto:
 *
 * 1. **El dato de salud es opcional en el TIPO** (`eps?`, `medical_notes?`…).
 *    Sin `bookings.passengers.medical.view` esos campos NO SE SERIALIZAN — no
 *    llegan `null`, no llegan. Declararlos opcionales obliga al compilador a
 *    exigir la guarda de `meta.can_view_medical` antes de pintarlos.
 * 2. **La fila de marcador de posición tiene `id: null`**. Es una reserva sin
 *    viajeros cargados: los campos de la PERSONA vienen en `null`, pero
 *    `booking_number`, `tour_date_id`, `departure_starts_at` y `payment` traen
 *    su valor real, porque son hechos de la reserva.
 */

export type PassengerPaymentStatus = 'paid' | 'due';

export type PassengerPayment = {
    /** `booking.total_amount / booking.travelers_count`, derivado (D5). */
    share_amount: string;
    paid_amount: string;
    due_amount: string;
    currency: string;
    status: PassengerPaymentStatus;
};

export type Passenger = {
    /** `null` en la fila de marcador de posición: la persona todavía no existe. */
    id: number | null;
    booking_number: string | null;
    tour_date_id: number | null;
    departure_starts_at: string | null;
    full_name: string;
    is_minor: boolean | null;
    document_type: DocumentType | null;
    document_type_label: string | null;
    document_number: string | null;
    email: string | null;
    phone: string | null;
    // Logística, no dato clínico: `sales` sí los recibe.
    emergency_contact_name: string | null;
    emergency_contact_relationship: string | null;
    emergency_contact_phone: string | null;
    // Bloque sensible: ausente sin el permiso médico.
    eps?: Eps | null;
    eps_label?: string | null;
    eps_other?: string | null;
    medical_notes?: string | null;
    dietary_restrictions: string | null;
    payment: PassengerPayment | null;
};

/** Espejo de `App\Data\PassengerManifestFilters::SEGMENTS`. */
export const PASSENGER_SEGMENTS = ['all', 'due', 'paid', 'obs'] as const;

export type PassengerSegment = (typeof PASSENGER_SEGMENTS)[number];

/**
 * Segmento que exige el permiso médico. Pedirlo sin él es `403` (D7): un filtro
 * que dice *quién* tiene una condición médica la delata sin mostrarla.
 */
export const MEDICAL_SEGMENT: PassengerSegment = 'obs';

export type PassengerManifestFilters = {
    segment: PassengerSegment;
    q: string;
    tourDateId: number | null;
    page: number;
};

export type DepartureOption = {
    id: number;
    starts_at: string;
    ends_at: string | null;
    capacity: number;
    booked_count: number;
    /** Nunca `null`: `tour_dates.guide_id` es `NOT NULL` desde la Fase 1 (D2). */
    guide?: { id: number; name: string } | null;
    status: string;
};

export type PassengerManifestSummary = {
    total_passengers: number;
    with_due: number;
    paid: number;
    /** Omitido sin el permiso médico: el conteo agregado también es dato clínico. */
    with_notes?: number;
    total_due_amount: string;
    currency: string;
};

export type PassengerManifestMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    can_view_medical: boolean;
    summary: PassengerManifestSummary;
    departures: DepartureOption[];
};

export type PassengerManifestResponse = {
    data: Passenger[];
    meta: PassengerManifestMeta;
};

/** Cuerpo de `POST /admin/bookings/{n}/passengers` y `PUT /admin/passengers/{id}`. */
export type PassengerFormInput = {
    full_name: string;
    is_minor: boolean;
    document_type: DocumentType | '';
    document_number: string;
    birth_date: string;
    email: string;
    phone: string;
    emergency_contact_name: string;
    emergency_contact_relationship: string;
    emergency_contact_phone: string;
    eps: Eps | '';
    eps_other: string;
    medical_notes: string;
};

/** Cuerpo de `POST /admin/bookings/{n}/payments`. */
export type ManualPaymentInput = {
    amount: string;
    reference: string;
    paid_at: string;
};
