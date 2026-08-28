import { translate } from '@/composables/useTranslations';
import { formatCurrency } from '@/lib/format';
import type {
    AccommodationType,
    CancellationPolicy,
    HotelAmenity,
    MealPlan,
    PaymentTerms,
    ProviderDocumentType,
    ProviderServiceType,
    RateUnit,
    RouteKind,
    RouteSeason,
    TaxRegime,
    TourDifficulty,
    TourStopKind,
} from '@/types/enums.generated';
import type {
    HotelResource,
    LogisticsFact,
    LogisticsRecord,
    LogisticsResourceKind,
    ProviderResource,
    RouteResource,
} from '@/types/logistics';

/**
 * Etiquetas de los enums de logística y los datos clave que cada ficha muestra
 * en la rejilla.
 *
 * WHY: las mismas doce listas se usan en el formulario (como opciones) y en la
 * tarjeta (como texto). Tenerlas dos veces garantizaba que un día dijeran
 * cosas distintas. Los valores son los de `app/Enums`, verificados contra
 * `types/enums.generated.ts`.
 */

export type SelectOption = { value: string; label: string };

function options(entries: Record<string, string>): SelectOption[] {
    return Object.entries(entries).map(([value, label]) => ({ value, label }));
}

export function routeKindLabels(): Record<RouteKind, string> {
    return {
        hiking: translate('Senderismo'),
        land: translate('Terrestre'),
        mixed: translate('Mixta'),
        water: translate('Acuática'),
        cycling: translate('Ciclismo'),
    };
}

export function difficultyLabels(): Record<TourDifficulty, string> {
    return {
        easy: translate('Fácil'),
        moderate: translate('Moderada'),
        hard: translate('Exigente'),
        extreme: translate('Alta montaña'),
    };
}

export function seasonLabels(): Record<RouteSeason, string> {
    return {
        all_year: translate('Todo el año'),
        december_february: translate('Dic–feb'),
        june_august: translate('Jun–ago'),
        avoid_rain: translate('Evitar lluvias'),
    };
}

export function stopKindLabels(): Record<TourStopKind, string> {
    return {
        pickup: translate('Recogida'),
        site: translate('Parada'),
        drop: translate('Regreso'),
    };
}

export function serviceTypeLabels(): Record<ProviderServiceType, string> {
    return {
        transport: translate('Transporte'),
        food: translate('Alimentación'),
        guiding: translate('Guianza'),
        activities: translate('Actividades'),
        equipment: translate('Equipos'),
        other: translate('Otro'),
    };
}

export function taxRegimeLabels(): Record<TaxRegime, string> {
    return {
        vat_responsible: translate('Responsable de IVA'),
        vat_exempt: translate('No responsable de IVA'),
        simple_regime: translate('Régimen simple'),
    };
}

export function paymentTermsLabels(): Record<PaymentTerms, string> {
    return {
        advance_30: translate('Anticipo 30 %'),
        advance_50: translate('Anticipo 50 % y saldo al finalizar'),
        prepaid: translate('Pago total anticipado'),
        credit_15: translate('Crédito 15 días'),
        credit_30: translate('Crédito 30 días'),
    };
}

export function rateUnitLabels(): Record<RateUnit, string> {
    return {
        per_day: translate('Por día'),
        per_person: translate('Por persona'),
        per_service: translate('Por servicio'),
        per_hour: translate('Por hora'),
    };
}

export function documentTypeLabels(): Record<ProviderDocumentType, string> {
    return {
        liability_policy: translate('Póliza de responsabilidad civil'),
        tax_registry: translate('RUT'),
        chamber_of_commerce: translate('Cámara de comercio'),
        health_registry: translate('Registro sanitario'),
        operation_card: translate('Tarjeta de operación'),
        other: translate('Otro'),
    };
}

export function accommodationTypeLabels(): Record<AccommodationType, string> {
    return {
        ecolodge: translate('Ecolodge'),
        hotel: translate('Hotel'),
        rural_inn: translate('Posada rural'),
        hostel: translate('Hostal'),
        glamping: translate('Glamping'),
        farm: translate('Finca'),
    };
}

export function mealPlanLabels(): Record<MealPlan, string> {
    return {
        breakfast_only: translate('Solo desayuno'),
        half_board: translate('Media pensión'),
        full_board: translate('Pensión completa'),
        none: translate('Sin alimentación'),
    };
}

export function cancellationPolicyLabels(): Record<CancellationPolicy, string> {
    return {
        free_48_hours: translate('Gratis hasta 48 h antes'),
        free_7_days: translate('Gratis hasta 7 días antes'),
        non_refundable: translate('No reembolsable'),
        per_contract: translate('Según contrato'),
    };
}

export function amenityLabels(): Record<HotelAmenity, string> {
    return {
        breakfast: translate('Desayuno'),
        lunch: translate('Almuerzo'),
        dinner: translate('Cena'),
        wifi: translate('Wi-Fi'),
        hot_water: translate('Agua caliente'),
        parking: translate('Parqueadero'),
        pool: translate('Piscina'),
        bonfire: translate('Fogata'),
        laundry: translate('Lavandería'),
        wheelchair_access: translate('Acceso silla de ruedas'),
    };
}

/** Estrellas del hotel; sin elegir ninguna queda «sin categorizar». */
export function starRatingOptions(): SelectOption[] {
    return [
        { value: '2', label: translate('2 estrellas') },
        { value: '3', label: translate('3 estrellas') },
        { value: '4', label: translate('4 estrellas') },
        { value: '5', label: translate('5 estrellas') },
    ];
}

export function currencyOptions(): SelectOption[] {
    return ['USD', 'COP', 'EUR', 'MXN', 'ARS', 'PEN', 'CLP', 'BRL'].map(
        (code) => ({ value: code, label: code }),
    );
}

export const routeKindOptions = (): SelectOption[] =>
    options(routeKindLabels());
export const difficultyOptions = (): SelectOption[] =>
    options(difficultyLabels());
export const seasonOptions = (): SelectOption[] => options(seasonLabels());
export const stopKindOptions = (): SelectOption[] => options(stopKindLabels());
export const serviceTypeOptions = (): SelectOption[] =>
    options(serviceTypeLabels());
export const taxRegimeOptions = (): SelectOption[] =>
    options(taxRegimeLabels());
export const paymentTermsOptions = (): SelectOption[] =>
    options(paymentTermsLabels());
export const rateUnitOptions = (): SelectOption[] => options(rateUnitLabels());
export const documentTypeOptions = (): SelectOption[] =>
    options(documentTypeLabels());
export const accommodationTypeOptions = (): SelectOption[] =>
    options(accommodationTypeLabels());
export const mealPlanOptions = (): SelectOption[] => options(mealPlanLabels());
export const cancellationPolicyOptions = (): SelectOption[] =>
    options(cancellationPolicyLabels());
export const amenityOptions = (): SelectOption[] => options(amenityLabels());

function money(amount: string | null, currency: string | null): string | null {
    if (amount === null || amount === '') {
        return null;
    }

    return formatCurrency(amount, currency ?? 'USD');
}

function shortDate(iso: string | null): string | null {
    if (iso === null || iso === '') {
        return null;
    }

    const date = new Date(`${iso}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return iso;
    }

    return new Intl.DateTimeFormat(undefined, {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(date);
}

function keep(facts: (LogisticsFact | null)[]): LogisticsFact[] {
    return facts.filter((fact): fact is LogisticsFact => fact !== null);
}

function fact(label: string, value: string | null): LogisticsFact | null {
    return value === null || value === '' ? null : { label, value };
}

function routeFacts(route: RouteResource): LogisticsFact[] {
    return keep([
        fact(
            translate('Distancia'),
            route.distance_km === null
                ? null
                : `${Number(route.distance_km)} km`,
        ),
        fact(
            translate('Duración'),
            route.duration_hours === null
                ? null
                : `${Number(route.duration_hours)} h`,
        ),
        fact(
            translate('Dificultad'),
            route.difficulty === null
                ? null
                : difficultyLabels()[route.difficulty],
        ),
        fact(
            translate('Paradas'),
            route.stops.length === 0 ? null : String(route.stops.length),
        ),
    ]);
}

function providerFacts(provider: ProviderResource): LogisticsFact[] {
    // El documento que primero vence es el que decide si el proveedor puede
    // salir el sábado; los demás pueden esperar a la ficha.
    const nextDocument = [...provider.documents]
        .filter((document) => document.expires_at !== null)
        .sort((a, b) => (a.expires_at ?? '').localeCompare(b.expires_at ?? ''))
        .at(0);

    const firstRate = provider.rates.at(0);

    return keep([
        fact(
            translate('Servicio'),
            provider.service_type === null
                ? null
                : serviceTypeLabels()[provider.service_type],
        ),
        fact(translate('NIT'), provider.tax_id),
        fact(
            translate('Tarifa'),
            firstRate === undefined
                ? null
                : (money(firstRate.amount, provider.currency) ??
                      firstRate.concept),
        ),
        nextDocument === undefined
            ? null
            : fact(
                  documentTypeLabels()[nextDocument.kind],
                  shortDate(nextDocument.expires_at),
              ),
    ]);
}

function hotelFacts(hotel: HotelResource): LogisticsFact[] {
    const rates = hotel.rooms
        .map((room) => room.nightly_rate)
        .filter((rate): rate is string => rate !== null && rate !== '')
        .map(Number)
        .filter((rate) => Number.isFinite(rate));

    const cheapest = rates.length === 0 ? null : Math.min(...rates);

    return keep([
        fact(
            translate('Categoría'),
            hotel.star_rating !== null
                ? translate(':count estrellas', { count: hotel.star_rating })
                : hotel.accommodation_type === null
                  ? null
                  : accommodationTypeLabels()[hotel.accommodation_type],
        ),
        fact(
            translate('Capacidad'),
            hotel.total_capacity === null
                ? null
                : translate(':count camas', { count: hotel.total_capacity }),
        ),
        fact(
            translate('Tarifa'),
            cheapest === null
                ? null
                : translate('desde :amount', {
                      amount: formatCurrency(cheapest, hotel.currency ?? 'USD'),
                  }),
        ),
        fact(translate('Check-in'), hotel.check_in),
    ]);
}

/** Lista de datos clave de la tarjeta, según el tipo de ficha. */
export function factsFor(
    kind: LogisticsResourceKind,
    record: LogisticsRecord,
): LogisticsFact[] {
    if (kind === 'routes') {
        return routeFacts(record as RouteResource);
    }

    if (kind === 'providers') {
        return providerFacts(record as ProviderResource);
    }

    return hotelFacts(record as HotelResource);
}

/** Municipio bajo el nombre de la ficha. */
export function localityOf(record: LogisticsRecord): string | null {
    const parts = [
        (record as RouteResource).city,
        (record as RouteResource).state,
    ].filter((part): part is string => part !== null && part !== '');

    return parts.length === 0 ? null : parts.join(', ');
}
