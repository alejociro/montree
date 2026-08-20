/**
 * Espejo de `App\Enums\DocumentType`: mismos valores y mismo orden. El pasajero
 * es una persona, así que no existe `nit`. Cambiar el enum en PHP obliga a
 * cambiar esta lista: los mapas de etiquetas son `Record<DocumentType, string>`
 * y el compilador se queja si falta o sobra un caso.
 */
export const DOCUMENT_TYPES = ['cc', 'ce', 'ti', 'passport', 'other'] as const;

export type DocumentType = (typeof DOCUMENT_TYPES)[number];

/**
 * Espejo de `App\Enums\Eps`, con la misma regla de negocio del backend.
 */
export const EPS_OPTIONS = [
    'sura',
    'nueva_eps',
    'sanitas',
    'salud_total',
    'other',
] as const;

export type Eps = (typeof EPS_OPTIONS)[number];

/**
 * Único caso que exige el texto libre `eps_other`
 * (`App\Enums\Eps::requiresDetail()`).
 */
export const EPS_REQUIRING_DETAIL: Eps = 'other';

export type BookingTraveler = {
    id: number;
    full_name: string;
    is_minor: boolean;
    email: string | null;
    phone: string | null;
    document_type: DocumentType | null;
    document_number: string | null;
    birth_date: string | null;
    // Bloque de emergencia y salud: `BookingTravelerResource` solo lo serializa
    // para el dueño de la reserva, así que puede no venir en la respuesta.
    emergency_contact_name?: string | null;
    emergency_contact_relationship?: string | null;
    emergency_contact_phone?: string | null;
    eps?: Eps | null;
    eps_label?: string | null;
    eps_other?: string | null;
    medical_notes?: string | null;
    dietary_restrictions?: string | null;
};

export type TravelerSyncInput = {
    id?: number;
    full_name: string;
    is_minor: boolean;
    phone?: string | null;
    document_type?: DocumentType | null;
    document_number?: string | null;
    birth_date?: string | null;
    email?: string | null;
    nationality?: string | null;
    dietary_restrictions?: string | null;
    medical_notes?: string | null;
    emergency_contact_name?: string | null;
    emergency_contact_relationship?: string | null;
    emergency_contact_phone?: string | null;
    eps?: Eps | null;
    eps_other?: string | null;
};
