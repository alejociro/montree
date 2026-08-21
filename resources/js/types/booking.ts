import { DOCUMENT_TYPE_VALUES, EPS_VALUES } from '@/types/enums.generated';
import type { DocumentType, Eps } from '@/types/enums.generated';

export type { DocumentType, Eps };

/**
 * Los tipos de documento y las EPS salen de `app/Enums` vía
 * `php artisan enums:typescript`; aquí solo se les deja el nombre con el que la
 * UI ya los pide. Los mapas de etiquetas son `Record<DocumentType, string>`, así
 * que agregar un caso en PHP rompe la compilación hasta que se traduzca.
 */
export const DOCUMENT_TYPES = DOCUMENT_TYPE_VALUES;

export const EPS_OPTIONS = EPS_VALUES;

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
