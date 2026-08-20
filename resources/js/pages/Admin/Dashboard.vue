<script setup lang="ts">
import { Head, useHttp, usePage } from '@inertiajs/vue3';
import { AlertCircle, Inbox } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import DashboardController from '@/actions/App/Http/Controllers/Api/V1/Admin/DashboardController';
import Heading from '@/components/Heading.vue';
import ExportRevenueButton from '@/components/molecules/ExportRevenueButton.vue';
import PeriodSelector from '@/components/molecules/PeriodSelector.vue';
import DashboardStatGrid from '@/components/organisms/DashboardStatGrid.vue';
import RevenueSparkline from '@/components/organisms/RevenueSparkline.vue';
import TopToursTable from '@/components/organisms/TopToursTable.vue';
import UpcomingDatesTable from '@/components/organisms/UpcomingDatesTable.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { useTenant } from '@/composables/useTenant';
import { useTranslations } from '@/composables/useTranslations';
import { intlLocale } from '@/lib/format';
import type {
    DashboardPeriodKey,
    DashboardResponse,
    DashboardSnapshot,
} from '@/types/dashboard';

const { t, tChoice } = useTranslations();

const period = ref<DashboardPeriodKey>('last_30_days');
const snapshot = ref<DashboardSnapshot | null>(null);
const isLoading = ref(false);
const errorMessage = ref<string | null>(null);

const { tenant } = useTenant();
const page = usePage();
const userName = computed(
    () =>
        (page.props.auth as { user?: { name?: string } } | undefined)?.user
            ?.name ?? '',
);

const sparklinePoints = computed<number[]>(() => {
    if (!snapshot.value) {
        return [];
    }

    return snapshot.value.revenue.series.map(
        (point) => Number.parseFloat(point.amount) || 0,
    );
});

const rangeLabel = computed(() => {
    if (!snapshot.value) {
        return '';
    }

    const start = new Date(snapshot.value.period.start).toLocaleDateString(
        intlLocale(),
        { day: 'numeric', month: 'short' },
    );
    const end = new Date(snapshot.value.period.end).toLocaleDateString(
        intlLocale(),
        { day: 'numeric', month: 'short', year: 'numeric' },
    );

    return `${start} – ${end}`;
});

const isRefreshing = computed(() => isLoading.value && snapshot.value !== null);

const pendingReviewsLabel = computed(() => {
    const count = snapshot.value?.pending_reviews_count ?? 0;

    return tChoice(':count reseña pendiente|:count reseñas pendientes', count);
});

async function loadDashboard(): Promise<void> {
    isLoading.value = true;
    errorMessage.value = null;

    try {
        const action = DashboardController.show({
            query: { period: period.value },
        });

        const response = (await useHttp().submit(action)) as DashboardResponse;
        snapshot.value = response.data;
    } catch {
        errorMessage.value = t('No se pudo cargar el dashboard.');
        snapshot.value = null;
    } finally {
        isLoading.value = false;
    }
}

onMounted(() => {
    loadDashboard();
});

watch(period, () => {
    loadDashboard();
});
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
                <PeriodSelector v-model="period" />
                <ExportRevenueButton
                    v-if="snapshot?.permissions.can_export_reports"
                />
            </div>
        </div>

        <Alert v-if="errorMessage" variant="destructive" class="mt-6">
            <AlertCircle class="size-4" />
            <AlertTitle>{{ $t('Error') }}</AlertTitle>
            <AlertDescription>{{ errorMessage }}</AlertDescription>
        </Alert>

        <div v-if="isLoading && !snapshot" class="mt-6 space-y-4">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
                <Skeleton v-for="n in 5" :key="n" class="h-28" />
            </div>
            <div class="grid gap-4 lg:grid-cols-2">
                <Skeleton class="h-72" />
                <Skeleton class="h-72" />
            </div>
        </div>

        <div
            v-else-if="snapshot"
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
                :revenue="snapshot.revenue"
                :bookings="snapshot.bookings"
                :rating="snapshot.rating"
                :occupancy="snapshot.occupancy"
            />

            <div class="grid gap-4 lg:grid-cols-[1fr_360px]">
                <div class="space-y-4">
                    <TopToursTable
                        :tours="snapshot.top_tours"
                        :currency="snapshot.revenue.currency"
                    />
                    <UpcomingDatesTable :dates="snapshot.upcoming_dates" />
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

                    <div
                        v-if="snapshot.pending_reviews_count > 0"
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

        <div
            v-else-if="!isLoading && !errorMessage"
            class="mt-12 rounded-xl border border-dashed border-border p-12 text-center"
        >
            <h3 class="text-base font-semibold">
                {{ $t('Bienvenido al dashboard') }}
            </h3>
            <p class="mt-2 text-sm text-muted-foreground">
                {{
                    $t(
                        'Cuando empieces a recibir reservas vas a ver aquí tus métricas.',
                    )
                }}
            </p>
        </div>
    </div>
</template>
