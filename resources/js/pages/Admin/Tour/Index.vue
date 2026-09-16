<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import {
    create as createPage,
    index as toursIndex,
} from '@/actions/App/Http/Controllers/Admin/TourPagesController';
import Heading from '@/components/Heading.vue';
import TourAdminCard from '@/components/organisms/TourAdminCard.vue';
import TourFilters from '@/components/organisms/TourFilters.vue';
import TourKpiGrid from '@/components/organisms/TourKpiGrid.vue';
import { Button } from '@/components/ui/button';
import { TOUR_SORT_PARAMS } from '@/types/tour';
import type {
    PaginatedTours,
    TourCategory,
    TourIndexFilters,
    TourIndexStats,
    TourSortValue,
    TourStatus,
} from '@/types/tour';

type ServerFilters = {
    status: TourStatus | null;
    category_id: number | null;
    search: string | null;
    sort: string;
    direction: 'asc' | 'desc';
};

type Props = {
    tours: PaginatedTours;
    filters: ServerFilters;
    categories: TourCategory[];
    /**
     * KPIs del encabezado. Opcional: sin `bookings.view` el servidor omite el
     * bloque de saldos y la fila se adapta en vez de mostrar ceros.
     */
    stats?: TourIndexStats;
};

const props = defineProps<Props>();

/**
 * El selector de orden de la barra combina columna y dirección en un solo
 * valor; el servidor las recibe separadas. Se traduce en los dos sentidos.
 */
function sortValueFrom(server: ServerFilters): TourSortValue {
    const match = (Object.keys(TOUR_SORT_PARAMS) as TourSortValue[]).find(
        (value) =>
            TOUR_SORT_PARAMS[value].sort === server.sort &&
            TOUR_SORT_PARAMS[value].direction === server.direction,
    );

    return match ?? 'recent';
}

const filters = ref<TourIndexFilters>({
    status: props.filters.status ?? 'all',
    category_id: props.filters.category_id,
    search: props.filters.search ?? '',
    sort: sortValueFrom(props.filters),
});

const meta = computed(() => props.tours.meta);

let searchDebounce: ReturnType<typeof setTimeout> | null = null;

function visit(page: number): void {
    const { sort, direction } = TOUR_SORT_PARAMS[filters.value.sort];
    const search = filters.value.search.trim();

    const query: Record<string, string> = { sort, direction };

    if (page > 1) {
        query.page = String(page);
    }

    if (filters.value.status !== 'all') {
        query.status = filters.value.status;
    }

    if (filters.value.category_id !== null) {
        query.category_id = String(filters.value.category_id);
    }

    if (search !== '') {
        query.search = search;
    }

    router.get(
        toursIndex.url({ query }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

// WHY: cambiar un filtro invalida la página actual — quedarse en la 4 de un
// resultado más corto devolvería una rejilla vacía.
function resetAndVisit(): void {
    visit(1);
}

function goToPage(target: number): void {
    if (target < 1 || target > meta.value.last_page) {
        return;
    }

    visit(target);
}

watch(
    () => filters.value.search,
    () => {
        if (searchDebounce) {
            clearTimeout(searchDebounce);
        }

        searchDebounce = setTimeout(resetAndVisit, 300);
    },
);

watch(() => filters.value.status, resetAndVisit);
watch(() => filters.value.category_id, resetAndVisit);
watch(() => filters.value.sort, resetAndVisit);
</script>

<template>
    <div class="px-4 py-6 md:px-8">
        <Head :title="$t('Tours')" />

        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="$t('Tours')"
                :description="
                    $t(
                        'Catálogo de experiencias y estado operativo de cada una.',
                    )
                "
            />
            <div class="flex items-center gap-2">
                <Link :href="createPage().url">
                    <Button>
                        <Plus class="size-4" />
                        {{ $t('Nuevo tour') }}
                    </Button>
                </Link>
            </div>
        </div>

        <TourKpiGrid v-if="props.stats" :stats="props.stats" class="mt-5" />

        <div class="mt-5">
            <TourFilters v-model="filters" :categories="props.categories" />
        </div>

        <div class="mt-5">
            <div
                v-if="props.tours.data.length === 0"
                class="flex flex-col items-center gap-4 rounded-xl border border-dashed border-input p-12 text-center"
            >
                <div class="space-y-1">
                    <p class="font-medium">{{ $t('Aún no hay tours') }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{
                            $t(
                                'Crea tu primer tour para empezar a recibir reservas.',
                            )
                        }}
                    </p>
                </div>
                <Link :href="createPage().url">
                    <Button>
                        <Plus class="size-4" />
                        {{ $t('Crear el primero') }}
                    </Button>
                </Link>
            </div>

            <div
                v-else
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3"
            >
                <TourAdminCard
                    v-for="tour in props.tours.data"
                    :key="tour.id"
                    :tour="tour"
                />
            </div>

            <div
                v-if="meta.total > 0"
                class="mt-5 flex flex-col items-center justify-between gap-3 sm:flex-row"
            >
                <p class="text-xs text-muted-foreground">
                    {{
                        $t('Mostrando :from – :to de :total tours', {
                            from: meta.from ?? 0,
                            to: meta.to ?? 0,
                            total: meta.total,
                        })
                    }}
                </p>

                <div v-if="meta.last_page > 1" class="flex items-center gap-2">
                    <Button
                        size="sm"
                        variant="outline"
                        :disabled="meta.current_page <= 1"
                        @click="goToPage(meta.current_page - 1)"
                    >
                        <ChevronLeft class="size-4" />
                        {{ $t('Anterior') }}
                    </Button>
                    <span class="text-xs text-muted-foreground">
                        {{
                            $t('Página :current de :last', {
                                current: meta.current_page,
                                last: meta.last_page,
                            })
                        }}
                    </span>
                    <Button
                        size="sm"
                        variant="outline"
                        :disabled="meta.current_page >= meta.last_page"
                        @click="goToPage(meta.current_page + 1)"
                    >
                        {{ $t('Siguiente') }}
                        <ChevronRight class="size-4" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
