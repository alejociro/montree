export function formatCurrency(
    amount: string | number,
    currency: string,
    locale = 'es-CO',
): string {
    const value =
        typeof amount === 'number' ? amount : Number.parseFloat(amount);

    if (!Number.isFinite(value)) {
        return `${currency} 0`;
    }

    try {
        return new Intl.NumberFormat(locale, {
            style: 'currency',
            currency,
            maximumFractionDigits: 0,
        }).format(value);
    } catch {
        return `${currency} ${value.toFixed(0)}`;
    }
}

export function formatNumber(value: number, locale = 'es-CO'): string {
    return new Intl.NumberFormat(locale).format(value);
}

export function formatPercent(value: number | null, locale = 'es-CO'): string {
    if (value === null) {
        return 'N/A';
    }

    return `${new Intl.NumberFormat(locale, { maximumFractionDigits: 1 }).format(value)}%`;
}

export function formatDateTime(iso: string, locale = 'es-CO'): string {
    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return iso;
    }

    return new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

export function formatDate(iso: string, locale = 'es-CO'): string {
    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return iso;
    }

    return new Intl.DateTimeFormat(locale, { dateStyle: 'medium' }).format(
        date,
    );
}

const RELATIVE_UNITS: Array<[Intl.RelativeTimeFormatUnit, number]> = [
    ['year', 365 * 24 * 60 * 60],
    ['month', 30 * 24 * 60 * 60],
    ['day', 24 * 60 * 60],
    ['hour', 60 * 60],
    ['minute', 60],
];

/**
 * Fecha relativa en espanol: "hace 3 días", "hace un momento".
 *
 * Para distancias de mas de un mes devuelve la fecha absoluta: "hace 7 meses"
 * dice menos que "12 de enero de 2026" cuando lo que se busca es saber si la
 * persona sigue usando la cuenta.
 */
export function formatRelativeDate(
    iso: string,
    now: Date = new Date(),
    locale = 'es-CO',
): string {
    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return iso;
    }

    const seconds = Math.round((date.getTime() - now.getTime()) / 1000);
    const absolute = Math.abs(seconds);

    if (absolute >= 30 * 24 * 60 * 60) {
        return formatDate(iso, locale);
    }

    if (absolute < 60) {
        return 'hace un momento';
    }

    const formatter = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });

    for (const [unit, unitSeconds] of RELATIVE_UNITS) {
        if (absolute >= unitSeconds) {
            return formatter.format(Math.round(seconds / unitSeconds), unit);
        }
    }

    return formatter.format(seconds, 'second');
}

type TourDateOptions = {
    withWeekday?: boolean;
    withTime?: boolean;
};

export function formatTourDate(
    iso: string,
    options: TourDateOptions = {},
    locale = 'es-CO',
): string {
    const { withWeekday = true, withTime = true } = options;
    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return iso;
    }

    const formatOptions: Intl.DateTimeFormatOptions = {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    };

    if (withWeekday) {
        formatOptions.weekday = 'long';
    }

    if (withTime) {
        formatOptions.hour = 'numeric';
        formatOptions.minute = '2-digit';
    }

    return new Intl.DateTimeFormat(locale, formatOptions).format(date);
}

const bookingStatusLabels: Record<string, string> = {
    pending_payment: 'Pendiente de pago',
    confirmed: 'Confirmada',
    completed: 'Completada',
    cancelled: 'Cancelada',
    expired: 'Expirada',
    refunded: 'Reembolsada',
};

export function formatBookingStatus(status: string): string {
    return bookingStatusLabels[status] ?? status;
}
