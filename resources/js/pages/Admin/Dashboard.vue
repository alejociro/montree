<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Inbox } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import ExportRevenueButton from '@/components/molecules/ExportRevenueButton.vue';
import PeriodSelector from '@/components/molecules/PeriodSelector.vue';
import DashboardStatGrid from '@/components/organisms/DashboardStatGrid.vue';
import RevenueByMethod from '@/components/organisms/RevenueByMethod.vue';
import RevenueSparkline from '@/components/organisms/RevenueSparkline.vue';
import TopToursTable from '@/components/organisms/TopToursTable.vue';
import UpcomingDatesTable from '@/components/organisms/UpcomingDatesTable.vue';
import { Spinner } from '@/components/ui/spinner';
import { useTenant } from '@/composables/useTenant';
import { useTranslations } from '@/composables/useTranslations';
import { intlLocale } from '@/lib/format';
import { dashboard } from '@/routes/admin';
import type { DashboardPageProps, DashboardPeriodKey } from '@/types/dashboard';

/**
 * Panel de la agencia.
 *
 * WHY props y no `useApi()`/`useHttp()`: es una ruta web que responde Inertia
 * (`DashboardPagesController`). El endpoint de API se retiró, así que no hay
 * carga inicial ni error de red que manejar: si la visita falla, la maneja
 * Inertia. Lo único que queda es el estado de refresco mientras se cambia el
 * periodo, que atenúa el contenido ya pintado en vez de vaciarlo.
 */
const props = defineProps<DashboardPageProps>();

const { tChoice } = useTranslations();

const isRefreshing = ref(false);

const { tenant } = useTenant();
const page = usePage();
const userName = computed(
    () =>
        (page.props.auth as { user?: { name?: string } } | undefined)?.user
            ?.name ?? '',
);

const sparklinePoints = computed<number[]>(() =>
    props.snapshot.revenue.series.map(
        (point) => Number.parseFloat(point.amount) || 0,
    ),
);

const rangeLabel = computed(() => {
    const start = new Date(props.snapshot.period.start).toLocaleDateString(
        intlLocale(),
        { day: 'numeric', month: 'short' },
    );
    const end = new Date(props.snapshot.period.end).toLocaleDateString(
        intlLocale(),
        { day: 'numeric', month: 'short', year: 'numeric' },
    );

    return `${start} – ${end}`;
});

const pendingReviewsLabel = computed(() =>
    tChoice(
        ':count reseña pendiente|:count reseñas pendientes',
        props.snapshot.pending_reviews_count,
    ),
);

function changePeriod(period: DashboardPeriodKey): void {
    if (period === props.filters.period) {
        return;
    }

    router.get(
        dashboard().url,
        { period },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: ['snapshot', 'filters', 'errors'],
            onStart: () => {
                isRefreshing.value = true;
            },
            onFinish: () => {
                isRefreshing.value = false;
            },
        },
    );
}
</script>

<template>
    <div class="px-4 py-6 md:px-8">
        <Head :title="$t('Dashboard administrativo')" />

        <div
            class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
        >
            <Heading
                :title="$t('Dashboard')"
                :description="
                    $t('Resumen de la operación de :agency.', {
                        agency: tenant?.name ?? $t('tu agencia'),
                    })
                "
            />

            <div class="flex flex-wrap items-center gap-2">
                <Spinner v-if="isRefreshing" class="text-muted-foreground" />
                <PeriodSelector
                    :model-value="props.filters.period"
                    :periods="props.periods"
                    @update:model-value="changePeriod"
                />
                <ExportRevenueButton
                    v-if="props.snapshot.permissions.can_export_reports"
                />
            </div>
        </div>

        <div
            class="mt-6 space-y-6 transition-opacity"
            :class="{ 'opacity-60': isRefreshing }"
        >
            <div v-if="userName" class="text-sm text-muted-foreground">
                {{
                    $t('Hola, :name. Esto pasó en :range.', {
                        name: userName,
                        range: rangeLabel,
                    })
                }}
            </div>

            <DashboardStatGrid
                :revenue="props.snapshot.revenue"
                :bookings="props.snapshot.bookings"
                :rating="props.snapshot.rating"
                :occupancy="props.snapshot.occupancy"
            />

            <div class="grid gap-4 lg:grid-cols-[1fr_360px]">
                <div class="space-y-4">
                    <TopToursTable
                        :tours="props.snapshot.top_tours"
                        :currency="props.snapshot.revenue.currency"
                    />
                    <UpcomingDatesTable
                        :dates="props.snapshot.upcoming_dates"
                    />
                </div>

                <div class="space-y-4">
                    <div
                        class="rounded-xl border border-border bg-card p-4 text-card-foreground"
                    >
                        <p class="text-sm font-medium text-muted-foreground">
                            {{ $t('Tendencia de ingresos') }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ $t('Comparación con el periodo anterior.') }}
                        </p>
                        <div class="mt-4">
                            <RevenueSparkline :points="sparklinePoints" />
                        </div>
                    </div>

                    <RevenueByMethod
                        :rows="props.snapshot.revenue.by_method"
                        :gross="props.snapshot.revenue.gross"
                        :currency="props.snapshot.revenue.currency"
                    />

                    <div
                        v-if="props.snapshot.pending_reviews_count > 0"
                        class="flex items-start gap-3 rounded-xl border border-accent-foreground/25 bg-accent p-4 text-sm text-accent-foreground"
                    >
                        <Inbox class="size-5 flex-none" />
                        <div>
                            <p class="font-medium">
                                {{ pendingReviewsLabel }}
                            </p>
                            <p class="mt-1 text-xs">
                                {{
                                    $t(
                                        'Revísalas para mantener tu reputación al día.',
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
