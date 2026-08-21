import type { ComputedRef, Ref } from 'vue';
import { computed, ref } from 'vue';
import { index as hotelsIndex } from '@/actions/App/Http/Controllers/Api/V1/Admin/HotelController';
import { index as providersIndex } from '@/actions/App/Http/Controllers/Api/V1/Admin/ProviderController';
import { index as routesIndex } from '@/actions/App/Http/Controllers/Api/V1/Admin/RouteController';
import { index as teamIndex } from '@/actions/App/Http/Controllers/Api/V1/Admin/TeamController';
import { index as datesIndex } from '@/actions/App/Http/Controllers/Api/V1/Admin/TourDateController';
import type { LogisticsRef, TourDateAdmin } from '@/types/logistics';

/** Recorte de `TeamMemberResource`: `roles` viaja como objetos. */
type TeamMember = { id: number; name: string; roles: { name: string }[] };

export type TourDepartureOptions = {
    guides: LogisticsRef[];
    routes: LogisticsRef[];
    providers: LogisticsRef[];
    hotels: LogisticsRef[];
};

export type UseTourDeparturesReturn = {
    departures: Ref<TourDateAdmin[]>;
    loading: Ref<boolean>;
    error: Ref<boolean>;
    options: Ref<TourDepartureOptions>;
    /** Programadas y no canceladas: es el número que va en la pestaña. */
    scheduledCount: ComputedRef<number>;
    /** Salidas abiertas a la venta, para la tarjeta de impacto. */
    openCount: ComputedRef<number>;
    load: () => Promise<void>;
    loadOptions: () => Promise<void>;
};

async function fetchJson<T>(url: string): Promise<T> {
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

    return (await response.json()) as T;
}

/**
 * Las salidas de un tour y los catálogos que necesita su formulario.
 *
 * WHY: la pantalla de edición necesita las salidas dos veces —la tabla y el
 * contador de la pestaña, además de la tarjeta de impacto—, y pedirlas desde
 * cada componente serían tres viajes para el mismo dato. El estado vive una
 * sola vez acá y baja como props.
 */
export function useTourDepartures(tourId: number): UseTourDeparturesReturn {
    const departures = ref<TourDateAdmin[]>([]);
    const loading = ref(true);
    const error = ref(false);
    const options = ref<TourDepartureOptions>({
        guides: [],
        routes: [],
        providers: [],
        hotels: [],
    });

    const scheduledCount = computed(
        () =>
            departures.value.filter(
                (departure) => departure.status !== 'cancelled',
            ).length,
    );

    const openCount = computed(
        () =>
            departures.value.filter((departure) => departure.status === 'open')
                .length,
    );

    async function load(): Promise<void> {
        loading.value = true;
        error.value = false;

        try {
            const payload = await fetchJson<{ data: TourDateAdmin[] }>(
                datesIndex(tourId, { query: { scope: 'all' } }).url,
            );
            departures.value = payload.data;
        } catch {
            error.value = true;
        } finally {
            loading.value = false;
        }
    }

    async function loadOptions(): Promise<void> {
        try {
            const [team, routes, providers, hotels] = await Promise.all([
                fetchJson<{ data: TeamMember[] }>(teamIndex().url),
                fetchJson<{ data: LogisticsRef[] }>(routesIndex().url),
                fetchJson<{ data: LogisticsRef[] }>(providersIndex().url),
                fetchJson<{ data: LogisticsRef[] }>(hotelsIndex().url),
            ]);

            options.value = {
                guides: team.data
                    .filter((member) =>
                        (member.roles ?? []).some(
                            (role) => role.name === 'guide',
                        ),
                    )
                    .map((member) => ({ id: member.id, name: member.name })),
                routes: routes.data.map((route) => ({
                    id: route.id,
                    name: route.name,
                })),
                providers: providers.data.map((provider) => ({
                    id: provider.id,
                    name: provider.name,
                })),
                hotels: hotels.data.map((hotel) => ({
                    id: hotel.id,
                    name: hotel.name,
                })),
            };
        } catch {
            // Sin catálogos el diálogo abre con los selects vacíos y el
            // servidor sigue validando: no hay nada que inventar acá.
            options.value = {
                guides: [],
                routes: [],
                providers: [],
                hotels: [],
            };
        }
    }

    return {
        departures,
        loading,
        error,
        options,
        scheduledCount,
        openCount,
        load,
        loadOptions,
    };
}
