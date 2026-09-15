<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ChevronLeft,
    ChevronRight,
    Receipt,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import TransactionFilters from '@/components/organisms/TransactionFilters.vue';
import TransactionsTable from '@/components/organisms/TransactionsTable.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { index as transactionsIndex } from '@/routes/admin/transactions';
import type {
    TransactionFilterValues,
    TransactionsPageProps,
} from '@/types/transaction';

/**
 * Listado de transacciones.
 *
 * WHY `router.get` y no `useApi()`: esto es una ruta web que responde Inertia,
 * no `/api/v1`. Los filtros son una visita parcial (`only`) que reemplaza la
 * entrada del historial, así que la URL siempre describe lo que se está viendo
 * y se puede compartir (constitución §4.2).
 */
const props = defineProps<TransactionsPageProps>();

const page = usePage();

const loading = ref(false);

const rows = computed(() => props.transactions.data ?? []);

const meta = computed(() => props.transactions.meta ?? null);

const paginationLinks = computed(() => props.transactions.links ?? null);

const total = computed(() => meta.value?.total ?? rows.value.length);

const validationErrors = computed<string[]>(() =>
    Object.values(page.props.errors ?? {}).map((message) => String(message)),
);

const emptyFilters: TransactionFilterValues = {
    search_by: null,
    search: null,
    status: null,
    gateway: null,
    tour_date_id: null,
    from: null,
    to: null,
};

const hasFilters = computed(
    () =>
        props.filters.search !== null ||
        props.filters.status !== null ||
        props.filters.gateway !== null ||
        props.filters.tour_date_id !== null ||
        props.filters.to !== null,
);

function toQuery(
    filters: TransactionFilterValues,
): Record<string, string | number> {
    const query: Record<string, string | number> = {};

    for (const [key, value] of Object.entries(filters)) {
        if (value !== null && value !== '') {
            query[key] = value;
        }
    }

    return query;
}

function applyFilters(filters: TransactionFilterValues): void {
    visit(transactionsIndex.url({ query: toQuery(filters) }));
}

function goToPage(url: string | null): void {
    if (url === null) {
        return;
    }

    visit(url);
}

function visit(url: string): void {
    router.get(
        url,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: ['transactions', 'filters', 'errors'],
            onStart: () => {
                loading.value = true;
            },
            onFinish: () => {
                loading.value = false;
            },
        },
    );
}
</script>

<template>
    <div>
        <Head :title="$t('Transacciones')" />

        <div class="px-4 py-6 md:px-8">
            <Heading
                :title="$t('Transacciones')"
                :description="
                    $t(
                        'Todos los pagos de la agencia, con los datos que pide el soporte de la pasarela.',
                    )
                "
            />

            <div class="mt-6 rounded-2xl border border-border bg-card">
                <TransactionFilters
                    :filters="props.filters"
                    :statuses="props.statuses"
                    :gateways="props.gateways"
                    :search-fields="props.search_fields"
                    :departures="props.departures"
                    :default-days="props.default_days"
                    @apply="applyFilters"
                />

                <div
                    v-if="validationErrors.length > 0"
                    class="flex items-start gap-3 border-b border-border bg-brand-drop-50 p-4"
                    role="alert"
                >
                    <AlertTriangle class="mt-0.5 size-5 text-brand-drop" />
                    <div class="space-y-1 text-sm text-brand-drop">
                        <p v-for="message in validationErrors" :key="message">
                            {{ message }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="loading"
                    class="space-y-2 p-4"
                    :aria-label="$t('Cargando transacciones')"
                    aria-busy="true"
                >
                    <Skeleton
                        v-for="row in 8"
                        :key="row"
                        class="h-12 w-full rounded-md"
                    />
                </div>

                <div
                    v-else-if="rows.length === 0"
                    class="flex flex-col items-center gap-3 p-12 text-center"
                >
                    <Receipt class="size-8 text-muted-foreground/40" />
                    <p class="font-medium text-foreground">
                        {{
                            $t('Ninguna transacción coincide con la búsqueda.')
                        }}
                    </p>
                    <p class="max-w-md text-sm text-muted-foreground">
                        {{
                            $t(
                                'Elegí el campo en «Buscar por» y pegá el valor exacto, o ampliá el rango de fechas.',
                            )
                        }}
                    </p>
                    <Button
                        v-if="hasFilters"
                        variant="outline"
                        size="sm"
                        @click="applyFilters(emptyFilters)"
                    >
                        {{ $t('Limpiar filtros') }}
                    </Button>
                </div>

                <TransactionsTable v-else :transactions="rows" />

                <div
                    v-if="!loading && rows.length > 0"
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-border p-4 text-sm text-muted-foreground"
                >
                    <span>
                        {{
                            $tc(
                                ':count transacción|:count transacciones',
                                total,
                            )
                        }}
                    </span>

                    <nav
                        v-if="(meta?.last_page ?? 1) > 1"
                        class="flex items-center gap-2"
                        :aria-label="$t('Paginación')"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="!paginationLinks?.prev"
                            @click="goToPage(paginationLinks?.prev ?? null)"
                        >
                            <ChevronLeft class="size-4" />
                            {{ $t('Anterior') }}
                        </Button>
                        <span class="tabular-nums">
                            {{ meta?.current_page ?? 1 }} /
                            {{ meta?.last_page ?? 1 }}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="!paginationLinks?.next"
                            @click="goToPage(paginationLinks?.next ?? null)"
                        >
                            {{ $t('Siguiente') }}
                            <ChevronRight class="size-4" />
                        </Button>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</template>
