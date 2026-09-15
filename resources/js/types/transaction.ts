import type {
    PaymentGateway,
    PaymentStatus,
    PaymentType,
    TransactionSearchField,
} from '@/types/enums.generated';
import type { PaginatedResponse } from '@/types/pagination';

/**
 * Espejo de `payments-transactions-admin/contracts.md`.
 *
 * Lo que NO está acá es tan contrato como lo que está: `process_url` (enlace de
 * cobro vivo) y `gateway_response` (volcado crudo con datos del pagador) no
 * viajan al cliente, y de `processor_fields` el backend solo emite
 * `lastDigits` — el BIN se queda en la base.
 */

export type TransactionBooking = {
    booking_number: string | null;
    holder_name: string | null;
    tour_name: string | null;
    tour_date_id: number | null;
    starts_at: string | null;
};

export type TransactionBookingDetail = TransactionBooking & {
    holder_email: string | null;
    total_amount: string;
    paid_amount: string;
    due_amount: string;
    status: string;
};

export type TransactionRow = {
    id: number;
    reference: string | null;
    request_id: string | null;
    gateway: PaymentGateway;
    gateway_label: string;
    status: PaymentStatus;
    status_label: string;
    gateway_status: string | null;
    status_message: string | null;
    amount: string;
    currency: string;
    type: PaymentType;
    processed_at: string | null;
    created_at: string | null;
    booking: TransactionBooking | null;
};

export type TransactionDetail = Omit<TransactionRow, 'booking'> & {
    internal_reference: string | null;
    type_label: string;
    authorization: string | null;
    receipt: string | null;
    franchise: string | null;
    payment_method: string | null;
    payment_method_name: string | null;
    issuer_name: string | null;
    last_digits: string | null;
    /** Solo `lastDigits` cuando existe; nunca el BIN. */
    processor_fields: Record<string, string>;
    session_expires_at: string | null;
    /**
     * Derivado en el backend (`Payment::isQueryable()`): de pasarela y con
     * `request_id`. El front NO reimplementa la regla, solo la obedece.
     */
    is_queryable: boolean;
    booking: TransactionBookingDetail | null;
};

export type TransactionFilterValues = {
    /**
     * No hay búsqueda libre: se elige un campo y se compara contra ese solo.
     * `search_by` sin `search` es un error de validación (`required_with`), así
     * que los dos viajan juntos o no viaja ninguno.
     */
    search_by: TransactionSearchField | null;
    search: string | null;
    status: PaymentStatus | null;
    gateway: PaymentGateway | null;
    tour_date_id: number | null;
    from: string | null;
    to: string | null;
};

export type TransactionOption = {
    value: string;
    label: string;
};

/** Salida con algo cobrado; el valor es el id, no una cadena. */
export type DepartureOption = {
    value: number;
    label: string;
};

export type TransactionAbilities = {
    query: boolean;
};

export type TransactionsPageProps = {
    transactions: PaginatedResponse<TransactionRow>;
    filters: TransactionFilterValues;
    statuses: TransactionOption[];
    gateways: TransactionOption[];
    search_fields: TransactionOption[];
    departures: DepartureOption[];
    /** Ventana en días que aplica el servidor cuando no se elige rango. */
    default_days: number;
    can: TransactionAbilities;
};

/** Una transacción de la reserva vista desde la planilla (`PaymentSummaryResource`). */
export type TransactionSummary = {
    id: number;
    reference: string | null;
    gateway: PaymentGateway;
    /** Etiqueta del enum: el front no mantiene su propio catálogo. */
    gateway_label: string;
    status: PaymentStatus;
    status_label: string;
    amount: string;
    processed_at: string | null;
};
