<script setup lang="ts">
import { Deferred, Head, router } from '@inertiajs/vue3';
import { SlidersHorizontal } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import SortDropdown from '@/components/molecules/SortDropdown.vue';
import ActiveFilters from '@/components/organisms/ActiveFilters.vue';
import CatalogSearchBar from '@/components/organisms/CatalogSearchBar.vue';
import FilterSidebar from '@/components/organisms/FilterSidebar.vue';
import TourGrid from '@/components/organisms/TourGrid.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useTenant } from '@/composables/useTenant';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { index as catalogIndex } from '@/routes/catalog';
import type {
    CatalogCategory,
    CatalogFilters,
    CatalogPaginatedTours,
    CatalogSort,
} from '@/types/catalog';
import type { TourDifficulty } from '@/types/tour';

defineOptions({ layout: PublicLayout });

type Props = {
    filters: CatalogFilters;
    tours?: CatalogPaginatedTours;
    categories?: CatalogCategory[];
};

const props = defineProps<Props>();

const { displayName, currency } = useTenant();
const fallbackCurrency = computed(() => currency.value ?? 'USD');

type LocalFilters = {
    search: string;
    category: string | null;
    difficulty: TourDifficulty | null;
    price_min: number | null;
    price_max: number | null;
    sort: CatalogSort;
};

const defaultFilters: LocalFilters = {
    search: props.filters.search ?? '',
    category: props.filters.category ?? null,
    difficulty: (props.filters.difficulty as TourDifficulty | null) ?? null,
    price_min: props.filters.price_min ?? null,
    price_max: props.filters.price_max ?? null,
    sort: (props.filters.sort as CatalogSort | null) ?? 'next_date_asc',
};

const local = ref<LocalFilters>({ ...defaultFilters });

const hasActiveFilters = computed(
    () =>
        local.value.search.trim() !== '' ||
        local.value.category !== null ||
        local.value.difficulty !== null ||
        local.value.price_min !== null ||
        local.value.price_max !== null,
);

const totalLabel = computed(() => {
    const total = props.tours?.meta.total ?? 0;

    if (total === 1) {
        return '1 tour disponible';
    }

    return `${total} tours disponibles`;
});

let searchDebounce: ReturnType<typeof setTimeout> | null = null;

function queryParams(): Record<string, string | number> {
    const params: Record<string, string | number> = {};

    const trimmedSearch = local.value.search.trim();

    if (trimmedSearch !== '') {
        params.search = trimmedSearch;
    }

    if (local.value.category) {
        params.category = local.value.category;
    }

    if (local.value.difficulty) {
        params.difficulty = local.value.difficulty;
    }

    if (local.value.price_min !== null) {
        params.price_min = local.value.price_min;
    }

    if (local.value.price_max !== null) {
        params.price_max = local.value.price_max;
    }

    if (local.value.sort !== 'next_date_asc') {
        params.sort = local.value.sort;
    }

    return params;
}

function applyFilters(): void {
    router.get(catalogIndex().url, queryParams(), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        only: ['filters', 'tours'],
    });
}

watch(
    () => local.value.search,
    () => {
        if (searchDebounce) {
            clearTimeout(searchDebounce);
        }

        searchDebounce = setTimeout(applyFilters, 300);
    },
);

watch(
    [
        () => local.value.category,
        () => local.value.difficulty,
        () => local.value.price_min,
        () => local.value.price_max,
        () => local.value.sort,
    ],
    () => applyFilters(),
);

function resetFilters(): void {
    local.value = {
        search: '',
        category: null,
        difficulty: null,
        price_min: null,
        price_max: null,
        sort: 'next_date_asc',
    };
}

function clearFilter(
    name: 'search' | 'category' | 'difficulty' | 'price',
): void {
    if (name === 'search') {
        local.value.search = '';

        return;
    }

    if (name === 'category') {
        local.value.category = null;

        return;
    }

    if (name === 'difficulty') {
        local.value.difficulty = null;

        return;
    }

    local.value.price_min = null;
    local.value.price_max = null;
}

function goToPage(url: string | null): void {
    if (!url) {
        return;
    }

    router.visit(url, {
        preserveScroll: false,
        preserveState: true,
        only: ['filters', 'tours'],
    });
}
</script>

<template>
    <Head :title="`Tours - ${displayName}`" />

    <section
        class="border-b border-border/60 bg-gradient-to-b from-primary/5 via-background to-background"
    >
        <div class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <p
                class="text-xs font-medium tracking-wider text-primary uppercase"
            >
                Catálogo
            </p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">
                Descubre {{ displayName }}
            </h1>
            <p
                class="mt-3 max-w-2xl text-sm text-muted-foreground sm:text-base"
            >
                Explora todos los tours disponibles, filtra por categoría o
                dificultad y reserva la experiencia perfecta para tu próxima
                aventura.
            </p>
            <div class="mt-6 max-w-2xl">
                <CatalogSearchBar v-model="local.search" />
            </div>
        </div>
    </section>

    <div class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-[260px_1fr]">
            <Deferred data="categories">
                <template #fallback>
                    <aside class="hidden space-y-4 lg:block">
                        <div
                            class="h-4 w-24 animate-pulse rounded bg-muted"
                        ></div>
                        <div class="space-y-2">
                            <div
                                v-for="n in 5"
                                :key="`cat-skel-${n}`"
                                class="h-7 w-full animate-pulse rounded-full bg-muted"
                            ></div>
                        </div>
                    </aside>
                </template>

                <aside class="hidden lg:block">
                    <FilterSidebar
                        :categories="categories ?? []"
                        :selected-category="local.category"
                        :selected-difficulty="local.difficulty"
                        :price-min="local.price_min"
                        :price-max="local.price_max"
                        :has-active-filters="hasActiveFilters"
                        @update:category="(value) => (local.category = value)"
                        @update:difficulty="
                            (value) => (local.difficulty = value)
                        "
                        @update:price-min="(value) => (local.price_min = value)"
                        @update:price-max="(value) => (local.price_max = value)"
                        @reset="resetFilters"
                    />
                </aside>
            </Deferred>

            <div class="space-y-5">
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <span class="text-sm text-muted-foreground">
                        {{ totalLabel }}
                    </span>
                    <div class="flex items-center gap-2">
                        <Sheet>
                            <SheetTrigger as-child>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="lg:hidden"
                                >
                                    <SlidersHorizontal class="mr-2 size-4" />
                                    Filtros
                                </Button>
                            </SheetTrigger>
                            <SheetContent side="left" class="w-80">
                                <SheetHeader>
                                    <SheetTitle>Filtros</SheetTitle>
                                </SheetHeader>
                                <div class="mt-6 px-4 pb-8">
                                    <Deferred data="categories">
                                        <template #fallback>
                                            <p
                                                class="text-sm text-muted-foreground"
                                            >
                                                Cargando filtros...
                                            </p>
                                        </template>
                                        <FilterSidebar
                                            :categories="categories ?? []"
                                            :selected-category="local.category"
                                            :selected-difficulty="
                                                local.difficulty
                                            "
                                            :price-min="local.price_min"
                                            :price-max="local.price_max"
                                            :has-active-filters="
                                                hasActiveFilters
                                            "
                                            @update:category="
                                                (value) =>
                                                    (local.category = value)
                                            "
                                            @update:difficulty="
                                                (value) =>
                                                    (local.difficulty = value)
                                            "
                                            @update:price-min="
                                                (value) =>
                                                    (local.price_min = value)
                                            "
                                            @update:price-max="
                                                (value) =>
                                                    (local.price_max = value)
                                            "
                                            @reset="resetFilters"
                                        />
                                    </Deferred>
                                </div>
                            </SheetContent>
                        </Sheet>
                        <SortDropdown v-model="local.sort" />
                    </div>
                </div>

                <ActiveFilters
                    :search="local.search.trim() || null"
                    :category="local.category"
                    :difficulty="local.difficulty"
                    :price-min="local.price_min"
                    :price-max="local.price_max"
                    :categories="categories ?? []"
                    :currency="fallbackCurrency"
                    @clear="clearFilter"
                />

                <Deferred data="tours">
                    <template #fallback>
                        <TourGrid :tours="[]" loading :skeleton-count="6" />
                    </template>

                    <div
                        v-if="(tours?.data.length ?? 0) === 0"
                        class="rounded-xl border border-dashed border-border bg-card p-10 text-center"
                    >
                        <h3
                            class="text-base font-semibold tracking-tight text-foreground"
                        >
                            No encontramos tours con esos filtros
                        </h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Intenta relajar los filtros o probar con otra
                            búsqueda.
                        </p>
                        <Button
                            v-if="hasActiveFilters"
                            class="mt-4"
                            variant="outline"
                            @click="resetFilters"
                        >
                            Limpiar filtros
                        </Button>
                    </div>

                    <template v-else>
                        <TourGrid :tours="tours?.data ?? []" />

                        <nav
                            v-if="(tours?.meta.last_page ?? 1) > 1"
                            class="flex items-center justify-between border-t border-border/60 pt-4"
                            aria-label="Paginación"
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="!tours?.links.prev"
                                @click="goToPage(tours?.links.prev ?? null)"
                            >
                                Anterior
                            </Button>
                            <span class="text-xs text-muted-foreground">
                                Página {{ tours?.meta.current_page }} de
                                {{ tours?.meta.last_page }}
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="!tours?.links.next"
                                @click="goToPage(tours?.links.next ?? null)"
                            >
                                Siguiente
                            </Button>
                        </nav>
                    </template>
                </Deferred>
            </div>
        </div>
    </div>
</template>
