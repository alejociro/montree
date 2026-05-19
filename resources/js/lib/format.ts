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
