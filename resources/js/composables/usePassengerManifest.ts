import type { ComputedRef, Ref } from 'vue';
import { computed, ref, watch } from 'vue';
import { index as adminManifest } from '@/actions/App/Http/Controllers/Api/V1/Admin/TourPassengerController';
import adminManifestExport from '@/actions/App/Http/Controllers/Api/V1/Admin/TourPassengerExportController';
import { index as guideManifest } from '@/actions/App/Http/Controllers/Api/V1/Guide/TourDatePassengerController';
import guideManifestExport from '@/actions/App/Http/Controllers/Api/V1/Guide/TourDatePassengerExportController';
import { translate } from '@/composables/useTranslations';
import type {
    DepartureOption,
    Passenger,
    PassengerManifestMeta,
    PassengerManifestResponse,
    PassengerManifestSummary,
    PassengerSegment,
} from '@/types/passenger';

/**
 * De dónde sale la planilla. Es lo único que cambia entre el panel —«todas las
 * salidas de este tour», con selector— y la zona del guía —«esta salida», sin
 * selector—, y por eso el organism puede ser uno solo.
 */
export type PassengerManifestSource =
    | { kind: 'tour'; tourId: number }
    | { kind: 'departure'; tourDateId: number };

export type UsePassengerManifestReturn = {
    passengers: Ref<Passenger[]>;
    meta: Ref<PassengerManifestMeta | null>;
    loading: Ref<boolean>;
    error: Ref<string | null>;
    segment: Ref<PassengerSegment>;
    search: Ref<string>;
    tourDateId: Ref<number | null>;
    page: Ref<number>;
    canViewMedical: ComputedRef<boolean>;
    summary: ComputedRef<PassengerManifestSummary | null>;
    departures: ComputedRef<DepartureOption[]>;
    lastPage: ComputedRef<number>;
    total: ComputedRef<number>;
    /**
     * Todo el resultado filtrado, no solo la página visible: es lo que imprime
     * la hoja `@media print`.
     */
    printRows: ComputedRef<Passenger[]>;
    exportUrl: ComputedRef<string>;
    reload: () => Promise<void>;
    goToPage: (value: number) => void;
};

/** Tope del contrato (`per_page` entre 10 y 100). */
const MAX_PER_PAGE = 100;

/** Cortafuegos del barrido de impresión: 20 páginas de 100 son 2.000 filas. */
const MAX_PRINT_PAGES = 20;

type QueryValue = string | number;

function buildQuery(
    source: PassengerManifestSource,
    segment: PassengerSegment,
    search: string,
    tourDateId: number | null,
    page: number,
    perPage: number,
): Record<string, QueryValue> {
    const query: Record<string, QueryValue> = { per_page: perPage, page };

    if (segment !== 'all') {
        query.segment = segment;
    }

    const term = search.trim();

    if (term !== '') {
        query.q = term;
    }

    // La zona del guía IGNORA `tour_date_id` (la salida va en la ruta), así que
    // ni se manda: el contrato tolera el parámetro de más, pero mandarlo sería
    // decir algo que no es.
    if (source.kind === 'tour' && tourDateId !== null) {
        query.tour_date_id = tourDateId;
    }

    return query;
}

function manifestUrl(
    source: PassengerManifestSource,
    query: Record<string, QueryValue>,
): string {
    return source.kind === 'tour'
        ? adminManifest.url(source.tourId, { query })
        : guideManifest.url(source.tourDateId, { query });
}

/**
 * Planilla de pasajeros: fetch, filtros, paginación y el conjunto completo para
 * imprimir. No decide nada de negocio — el segmento, el resumen y la máscara del
 * dato de salud los resuelve el backend y aquí solo se transportan.
 */
export function usePassengerManifest(
    source: PassengerManifestSource,
): UsePassengerManifestReturn {
    const passengers = ref<Passenger[]>([]);
    const meta = ref<PassengerManifestMeta | null>(null);
    const loading = ref(true);
    const error = ref<string | null>(null);

    const segment = ref<PassengerSegment>('all');
    const search = ref('');
    const tourDateId = ref<number | null>(null);
    const page = ref(1);

    // Filas del resto de páginas, solo cuando la planilla no cabe en una.
    const overflowRows = ref<Passenger[]>([]);

    const canViewMedical = computed(
        () => meta.value?.can_view_medical ?? false,
    );
    const summary = computed(() => meta.value?.summary ?? null);
    const departures = computed(() => meta.value?.departures ?? []);
    const lastPage = computed(() => meta.value?.last_page ?? 1);
    const total = computed(() => meta.value?.total ?? 0);

    const printRows = computed<Passenger[]>(() =>
        overflowRows.value.length > 0 ? overflowRows.value : passengers.value,
    );

    const exportUrl = computed(() => {
        const query = buildQuery(
            source,
            segment.value,
            search.value,
            tourDateId.value,
            1,
            MAX_PER_PAGE,
        );

        // El export ignora `per_page` y `page`: exporta todo el filtrado.
        delete query.per_page;
        delete query.page;

        return source.kind === 'tour'
            ? adminManifestExport.url(source.tourId, { query })
            : guideManifestExport.url(source.tourDateId, { query });
    });

    async function fetchPage(
        target: number,
        perPage: number,
    ): Promise<PassengerManifestResponse> {
        const url = manifestUrl(
            source,
            buildQuery(
                source,
                segment.value,
                search.value,
                tourDateId.value,
                target,
                perPage,
            ),
        );

        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        return (await response.json()) as PassengerManifestResponse;
    }

    /**
     * WHY: la hoja de impresión tiene que llevar TODO el resultado filtrado, y
     * `window.print()` no se puede esperar desde `beforeprint`. Así que en
     * cuanto la planilla pasa de una página se trae el resto en segundo plano y
     * la hoja siempre está lista, la dispare el botón o el atajo del navegador.
     */
    async function loadOverflow(): Promise<void> {
        if (lastPage.value <= 1) {
            overflowRows.value = [];

            return;
        }

        const pages = Math.min(
            Math.ceil(total.value / MAX_PER_PAGE),
            MAX_PRINT_PAGES,
        );
        const rows: Passenger[] = [];

        for (let current = 1; current <= pages; current += 1) {
            const payload = await fetchPage(current, MAX_PER_PAGE);
            rows.push(...payload.data);
        }

        overflowRows.value = rows;
    }

    async function reload(): Promise<void> {
        loading.value = true;
        error.value = null;

        try {
            const payload = await fetchPage(page.value, 50);
            passengers.value = payload.data;
            meta.value = payload.meta;

            await loadOverflow();
        } catch {
            passengers.value = [];
            overflowRows.value = [];
            error.value = translate('No pudimos cargar la planilla.');
        } finally {
            loading.value = false;
        }
    }

    function goToPage(value: number): void {
        page.value = Math.min(Math.max(1, value), lastPage.value);
    }

    // Cambiar de filtro siempre vuelve a la página 1: la 3 de un filtro no es
    // la 3 del siguiente, y quedarse ahí muestra un vacío que no es vacío.
    watch([segment, search, tourDateId], () => {
        if (page.value === 1) {
            void reload();

            return;
        }

        page.value = 1;
    });

    watch(page, () => {
        void reload();
    });

    // Sin `window` no hay `fetch` con cookies de sesión: en un render de
    // servidor la planilla se queda en su estado de carga, que es justo el
    // esqueleto de filas que el organism ya sabe pintar.
    if (typeof window !== 'undefined') {
        void reload();
    }

    return {
        passengers,
        meta,
        loading,
        error,
        segment,
        search,
        tourDateId,
        page,
        canViewMedical,
        summary,
        departures,
        lastPage,
        total,
        printRows,
        exportUrl,
        reload,
        goToPage,
    };
}
