export type DocumentType = 'cc' | 'ce' | 'passport' | 'ti' | 'other';

export type BookingTraveler = {
    id: number;
    full_name: string;
    is_minor: boolean;
    email: string | null;
    phone: string | null;
    document_type: string | null;
    document_number: string | null;
    birth_date: string | null;
};

export type TravelerSyncInput = {
    id?: number;
    full_name: string;
    is_minor: boolean;
    phone?: string | null;
    document_type?: string | null;
    document_number?: string | null;
    birth_date?: string | null;
    email?: string | null;
    nationality?: string | null;
    dietary_restrictions?: string | null;
    medical_notes?: string | null;
};
