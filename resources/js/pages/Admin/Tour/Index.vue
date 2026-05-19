<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Pencil, Plus } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { create as createPage } from '@/actions/App/Http/Controllers/Admin/TourPagesController';
import { edit as editPage } from '@/actions/App/Http/Controllers/Admin/TourPagesController';
import { index as toursIndex } from '@/actions/App/Http/Controllers/Api/V1/Admin/TourController';
import Heading from '@/components/Heading.vue';
import TourFilters from '@/components/organisms/TourFilters.vue';
import TourStatusBadge from '@/components/organisms/TourStatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTenant } from '@/composables/useTenant';
import type {
    PaginatedTours,
    TourCategory,
    TourStatus,
    TourSummary,
} from '@/types/tour';

type Props = {
    categories: TourCategory[];
};

const props = defineProps<Props>();

type FilterValue = {
    status: TourStatus | 'all';
    category_id: number | null;
    search: string;
};

const filters = ref<FilterValue>({
    status: 'all',
    category_id: null,
    search: '',
});

const tours = ref<TourSummary[]>([]);
const meta = ref<PaginatedTours['meta'] | null>(null);
const loading = ref(false);
const { currency } = useTenant();

let searchDebounce: ReturnType<typeof setTimeout> | null = null;

async function fetchTours(): Promise<void> {
    loading.value = true;

    try {
        const params: Record<string, string> = {};

        if (filters.value.status !== 'all') {
            params.status = filters.value.status;
        }

        if (filters.value.category_id !== null) {
            params.category_id = String(filters.value.category_id);
        }

        if (filters.value.search.trim() !== '') {
            params.search = filters.value.search.trim();
        }

        const url = toursIndex(params).url;
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const payload = (await response.json()) as PaginatedTours;
        tours.value = payload.data;
        meta.value = payload.meta;
    } catch {
        toast.error('No se pudieron cargar los tours.');
    } finally {
        loading.value = false;
    }
}

watch(
    () => filters.value.search,
    () => {
        if (searchDebounce) {
            clearTimeout(searchDebounce);
        }

        searchDebounce = setTimeout(fetchTours, 300);
    },
);

watch(() => filters.value.status, fetchTours);
watch(() => filters.value.category_id, fetchTours);

onMounted(fetchTours);

function formatPrice(amount: string, code: string): string {
    const value = Number(amount);

    if (Number.isNaN(value)) {
        return `${code} ${amount}`;
    }

    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: code,
        maximumFractionDigits: 0,
    }).format(value);
}
</script>

<template>
    <Head title="Tours" />

    <div class="px-4 py-6 md:px-8">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Tours"
                description="Gestioná el catálogo de experiencias de tu agencia."
            />
            <Link :href="createPage().url">
                <Button>
                    <Plus class="size-4" />
                    Nuevo tour
                </Button>
            </Link>
        </div>

        <div class="mt-6">
            <TourFilters v-model="filters" :categories="props.categories" />
        </div>

        <div class="mt-6">
            <div
                v-if="loading && tours.length === 0"
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
            >
                <div
                    v-for="i in 4"
                    :key="i"
                    class="h-48 animate-pulse rounded-lg bg-muted"
                />
            </div>

            <div
                v-else-if="tours.length === 0"
                class="flex flex-col items-center gap-4 rounded-lg border border-dashed border-input p-12 text-center"
            >
                <div class="rounded-full bg-muted p-4">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="32"
                        height="32"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="text-muted-foreground"
                    >
                        <path d="m8 3 4 8 5-5 5 15H2L8 3z" />
                        <path
                            d="M4.14 15.08c2.62-1.57 5.24-1.43 7.86.42 2.74 1.94 5.49 2 8.23.19"
                        />
                    </svg>
                </div>
                <div class="space-y-1">
                    <p class="font-medium">Aún no hay tours</p>
                    <p class="text-sm text-muted-foreground">
                        Creá tu primer tour para empezar a recibir reservas.
                    </p>
                </div>
                <Link :href="createPage().url">
                    <Button>
                        <Plus class="size-4" />
                        Crear el primero
                    </Button>
                </Link>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Card
                    v-for="tour in tours"
                    :key="tour.id"
                    class="overflow-hidden p-0"
                >
                    <div class="aspect-[16/9] bg-muted">
                        <img
                            v-if="tour.cover_image_url"
                            :src="tour.cover_image_url"
                            :alt="tour.name"
                            class="size-full object-cover"
                        />
                        <div
                            v-else
                            class="flex size-full items-center justify-center text-xs text-muted-foreground"
                        >
                            Sin portada
                        </div>
                    </div>

                    <CardHeader class="px-4 pt-4 pb-2">
                        <div class="flex items-start justify-between gap-3">
                            <CardTitle class="text-base leading-tight">
                                {{ tour.name }}
                            </CardTitle>
                            <TourStatusBadge :status="tour.status" />
                        </div>
                        <CardDescription class="line-clamp-2">
                            {{ tour.short_description || 'Sin resumen' }}
                        </CardDescription>
                    </CardHeader>

                    <CardContent
                        class="flex items-center justify-between gap-3 px-4 pb-4"
                    >
                        <div class="text-sm">
                            <p class="font-medium">
                                {{
                                    formatPrice(
                                        tour.base_price,
                                        tour.currency || currency || 'USD',
                                    )
                                }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ tour.duration_hours }}h ·
                                {{ tour.default_capacity }} pers.
                            </p>
                        </div>
                        <Link :href="editPage({ tour: tour.id }).url">
                            <Button size="sm" variant="outline">
                                <Pencil class="size-4" />
                                Editar
                            </Button>
                        </Link>
                    </CardContent>
                </Card>
            </div>

            <div
                v-if="meta && meta.total > 0"
                class="mt-6 text-xs text-muted-foreground"
            >
                Mostrando {{ meta.from ?? 0 }} – {{ meta.to ?? 0 }} de
                {{ meta.total }} tours
            </div>
        </div>
    </div>
</template>
