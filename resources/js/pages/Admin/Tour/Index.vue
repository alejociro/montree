<script setup lang="ts">
import { Head, Link, useHttp } from '@inertiajs/vue3';
import { AlertCircle, ChevronLeft, ChevronRight, Plus } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';
import { create as createPage } from '@/actions/App/Http/Controllers/Admin/TourPagesController';
import TourController from '@/actions/App/Http/Controllers/Api/V1/Admin/TourController';
import Heading from '@/components/Heading.vue';
import TourAdminCard from '@/components/organisms/TourAdminCard.vue';
import TourFilters from '@/components/organisms/TourFilters.vue';
import TourKpiGrid from '@/components/organisms/TourKpiGrid.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { useTenant } from '@/composables/useTenant';
import { useTranslations } from '@/composables/useTranslations';
import { TOUR_SORT_PARAMS } from '@/types/tour';
import type {
    PaginatedTours,
    TourCategory,
    TourIndexFilters,
    TourIndexStats,
    TourSummary,
} from '@/types/tour';

const { t } = useTranslations();

type Props = {
    categories: TourCategory[];
    /**
     * KPIs del encabezado. Opcional: el controlador todavía no los calcula, y
     * la fila se omite en vez de mostrar ceros que no son la verdad.
     */
    stats?: TourIndexStats;
};

const props = defineProps<Props>();

const filters = ref<TourIndexFilters>({
    status: 'all',
    category_id: null,
    search: '',
    sort: 'recent',
});

const tours = ref<TourSummary[]>([]);
const meta = ref<PaginatedTours['meta'] | null>(null);
const loading = ref(false);
const loaded = ref(false);
const errorMessage = ref<string | null>(null);
const page = ref(1);
const { currency } = useTenant();

let searchDebounce: ReturnType<typeof setTimeout> | null = null;

async function fetchTours(): Promise<void> {
    loading.value = true;
    errorMessage.value = null;

    const { sort, direction } = TOUR_SORT_PARAMS[filters.value.sort];

    const query: Record<string, string> = {
        page: String(page.value),
        sort,
        direction,
    };

    if (filters.value.status !== 'all') {
        query.status = filters.value.status;
    }

    if (filters.value.category_id !== null) {
        query.category_id = String(filters.value.category_id);
    }

    if (filters.value.search.trim() !== '') {
        query.search = filters.value.search.trim();
    }

    try {
        const response = (await useHttp().submit(
            TourController.index({ query }),
        )) as PaginatedTours;

        tours.value = response.data;
        meta.value = response.meta;
    } catch {
        errorMessage.value = t('No se pudieron cargar los tours.');
        tours.value = [];
        meta.value = null;
    } finally {
        loading.value = false;
        loaded.value = true;
    }
}

// WHY: any filter change invalidates the current page — staying on page 4 of a
// narrower result set would render an empty list.
function resetAndFetch(): void {
    page.value = 1;
    void fetchTours();
}

function goToPage(target: number): void {
    const lastPage = meta.value?.last_page ?? 1;

    if (target < 1 || target > lastPage || target === page.value) {
        return;
    }

    page.value = target;
    void fetchTours();
}

watch(
    () => filters.value.search,
    () => {
        if (searchDebounce) {
            clearTimeout(searchDebounce);
        }

        searchDebounce = setTimeout(resetAndFetch, 300);
    },
);

watch(() => filters.value.status, resetAndFetch);
watch(() => filters.value.category_id, resetAndFetch);
watch(() => filters.value.sort, resetAndFetch);

onMounted(fetchTours);
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
                <Spinner
                    v-if="loading && loaded"
                    class="text-muted-foreground"
                />
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

        <Alert v-if="errorMessage" variant="destructive" class="mt-6">
            <AlertCircle class="size-4" />
            <AlertTitle>{{ $t('Error') }}</AlertTitle>
            <AlertDescription class="flex flex-col items-start gap-3">
                {{ errorMessage }}
                <Button size="sm" variant="outline" @click="fetchTours()">
                    {{ $t('Reintentar') }}
                </Button>
            </AlertDescription>
        </Alert>

        <div v-else class="mt-5">
            <div
                v-if="!loaded"
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3"
            >
                <div
                    v-for="i in 6"
                    :key="i"
                    class="overflow-hidden rounded-xl border border-border"
                >
                    <Skeleton class="aspect-[16/9] w-full rounded-none" />
                    <div class="space-y-2.5 p-4">
                        <Skeleton class="h-4 w-3/4" />
                        <Skeleton class="h-3 w-1/2" />
                        <Skeleton class="h-2 w-full" />
                    </div>
                </div>
            </div>

            <div
                v-else-if="tours.length === 0"
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
                    v-for="tour in tours"
                    :key="tour.id"
                    :tour="tour"
                    :fallback-currency="currency ?? 'USD'"
                />
            </div>

            <div
                v-if="meta && meta.total > 0"
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
                        :disabled="loading || meta.current_page <= 1"
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
                        :disabled="
                            loading || meta.current_page >= meta.last_page
                        "
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
