<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarClock,
    CalendarPlus,
    Check,
    ExternalLink,
    Gauge,
    MapPin,
    Mountain,
    Pencil,
    Star,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import {
    edit as editPage,
    index as toursIndex,
} from '@/actions/App/Http/Controllers/Admin/TourPagesController';
import KpiCard from '@/components/atoms/KpiCard.vue';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import OccupancyBar from '@/components/molecules/OccupancyBar.vue';
import TourTabs from '@/components/molecules/TourTabs.vue';
import type { TourTabItem } from '@/components/molecules/TourTabs.vue';
import PassengerManifest from '@/components/organisms/PassengerManifest.vue';
import TourRouteMapSection from '@/components/organisms/TourRouteMapSection.vue';
import TourStatusBadge from '@/components/organisms/TourStatusBadge.vue';
import TourUpcomingDatesList from '@/components/organisms/TourUpcomingDatesList.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { useTenant } from '@/composables/useTenant';
import { useTourManifestSummary } from '@/composables/useTourManifestSummary';
import { useTranslations } from '@/composables/useTranslations';
import { categoryLabel } from '@/lib/categories';
import { formatCurrency, formatNumber, formatTourDate } from '@/lib/format';
import { routeStopsFromTour } from '@/lib/tour-route';
import { tourTabId, tourTabPanelId } from '@/lib/tour-tabs';
import { show as publicTourShow } from '@/routes/tours';
import type { Tour, TourDifficulty, TourShowStats } from '@/types/tour';

const { t } = useTranslations();

type Props = {
    tour: Tour;
    stats: TourShowStats;
};

const props = defineProps<Props>();

const { currency: tenantCurrency } = useTenant();

const currency = computed(
    () => props.tour.currency || tenantCurrency.value || 'USD',
);

const difficultyLabels: Record<TourDifficulty, string> = {
    easy: t('Fácil'),
    moderate: t('Moderado'),
    hard: t('Difícil'),
    extreme: t('Extremo'),
};

const difficultyLabel = computed(
    () => difficultyLabels[props.tour.difficulty] ?? props.tour.difficulty,
);

const sortedImages = computed(() =>
    [...props.tour.images].sort((a, b) => {
        if (a.is_cover !== b.is_cover) {
            return a.is_cover ? -1 : 1;
        }

        return a.display_order - b.display_order;
    }),
);

const coverImage = computed(
    () =>
        sortedImages.value.find((image) => image.is_cover) ??
        sortedImages.value[0] ??
        null,
);

const galleryImages = computed(() =>
    sortedImages.value.filter((image) => image.id !== coverImage.value?.id),
);

const ratingValue = computed(() => Number(props.tour.rating_average) || 0);

const stars = computed(() =>
    Array.from(
        { length: 5 },
        (_, index) => index + 1 <= Math.round(ratingValue.value),
    ),
);

const occupancy = computed(() => props.stats.occupancy_upcoming);

const mapsUrl = computed(() => {
    const lat = props.tour.meeting_latitude;
    const lng = props.tour.meeting_longitude;

    if (!lat || !lng) {
        return null;
    }

    return `https://www.google.com/maps/search/?api=1&query=${lat},${lng}`;
});

const { can } = usePermissions();

/**
 * Pestaña «Pasajeros»: solo con `bookings.view`. Regla de oro del menú (F018):
 * si el item aparece, la ruta responde 200 — y sin ese permiso la API de la
 * planilla devuelve 403.
 */
const canViewPassengers = computed(() => can('bookings.view'));

// ------------------------------------------------------------- Planilla

const {
    summary,
    loading: summaryLoading,
    load: loadSummary,
} = useTourManifestSummary(props.tour.id);

onMounted(() => {
    if (canViewPassengers.value) {
        void loadSummary();
    }
});

// ------------------------------------------------------------------ KPIs

type ShowKpi = {
    key: string;
    label: string;
    value: string;
    detail: string | null;
    alert?: boolean;
    loading?: boolean;
};

/**
 * Cuatro cifras, todas del backend. La cuarta es el saldo pendiente cuando la
 * planilla está permitida —sale del `meta.summary` que el endpoint ya
 * calcula— y la calificación cuando no lo está: sin `bookings.view` no hay
 * ninguna cifra de dinero por pasajero que mostrar, y un cero se leería como
 * «nadie debe nada».
 */
const kpis = computed<ShowKpi[]>(() => {
    const items: ShowKpi[] = [
        {
            key: 'bookings',
            label: t('Reservas'),
            value: formatNumber(props.stats.bookings.total),
            detail: t(
                ':confirmed confirmadas · :pending pendientes · :cancelled canceladas',
                {
                    confirmed: props.stats.bookings.confirmed,
                    pending: props.stats.bookings.pending_payment,
                    cancelled: props.stats.bookings.cancelled,
                },
            ),
        },
        {
            key: 'travelers',
            label: t('Pasajeros'),
            value: formatNumber(props.stats.travelers_total),
            detail: t('En reservas confirmadas y completadas'),
        },
        {
            key: 'revenue',
            label: t('Ingresos cobrados'),
            value: formatCurrency(
                props.stats.revenue_total,
                props.stats.currency,
            ),
            detail: t('Pagos completados'),
        },
    ];

    if (canViewPassengers.value) {
        items.push({
            key: 'due',
            label: t('Saldo pendiente'),
            value:
                summary.value === null
                    ? '—'
                    : formatCurrency(
                          summary.value.total_due_amount,
                          summary.value.currency,
                      ),
            detail:
                summary.value === null
                    ? t('Sin datos de la planilla.')
                    : t(':count pasajeros con saldo', {
                          count: summary.value.with_due,
                      }),
            alert: summary.value !== null && summary.value.with_due > 0,
            loading: summaryLoading.value,
        });

        return items;
    }

    items.push({
        key: 'rating',
        label: t('Calificación'),
        value: ratingValue.value.toFixed(1),
        detail: t(':count reseñas', { count: props.tour.rating_count }),
    });

    return items;
});

// -------------------------------------------------------------- Pestañas

type TourShowTab = 'summary' | 'passengers' | 'route';

const activeTab = ref<TourShowTab>('summary');

const routeStops = computed(() => routeStopsFromTour(props.tour));

const tabs = computed<TourTabItem[]>(() => {
    const items: TourTabItem[] = [{ id: 'summary', label: t('Resumen') }];

    if (canViewPassengers.value) {
        items.push({
            id: 'passengers',
            label: t('Pasajeros'),
            // Sin resumen no se dibuja el contador: un `0` prestado miente.
            count: summary.value?.total_passengers ?? null,
        });
    }

    if (routeStops.value.length > 0) {
        items.push({ id: 'route', label: t('Ruta') });
    }

    return items;
});

function selectTab(tab: string): void {
    activeTab.value = tab as TourShowTab;
}

const mapSection = ref<InstanceType<typeof TourRouteMapSection> | null>(null);

/**
 * WHY: un mapa de Leaflet montado dentro de una pestaña oculta mide 0×0 y se
 * queda en gris. Al activarse «Ruta» hay que re-medir y volver a encuadrar.
 */
watch(activeTab, (tab) => {
    if (tab !== 'route') {
        return;
    }

    void nextTick(() => mapSection.value?.fit());
});

const manifestSource = computed(
    () => ({ kind: 'tour', tourId: props.tour.id }) as const,
);
</script>

<template>
    <div class="px-4 py-6 md:px-8">
        <Head :title="props.tour.name" />

        <Link
            :href="toursIndex().url"
            class="mb-4 inline-flex items-center gap-1.5 text-sm text-muted-foreground transition hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            {{ $t('Volver a tours') }}
        </Link>

        <!-- Hero -->
        <section
            class="relative overflow-hidden rounded-2xl border border-border"
        >
            <div class="aspect-[21/9] w-full sm:aspect-[21/7]">
                <img
                    v-if="coverImage"
                    :src="coverImage.url"
                    :alt="coverImage.alt_text ?? props.tour.name"
                    class="size-full object-cover"
                />
                <div
                    v-else
                    class="flex size-full items-center justify-center bg-gradient-to-br from-primary/80 via-primary/50 to-primary/20"
                >
                    <Mountain class="size-16 text-primary-foreground/60" />
                </div>
            </div>

            <div
                class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent"
            />

            <div class="absolute inset-x-0 top-0 flex justify-end gap-2 p-4">
                <Link
                    :href="publicTourShow(props.tour.slug).url"
                    target="_blank"
                    rel="noopener"
                >
                    <Button variant="secondary" size="sm">
                        <ExternalLink class="size-4" />
                        {{ $t('Ver como viajero') }}
                    </Button>
                </Link>
                <Link :href="editPage({ tour: props.tour.id }).url">
                    <Button size="sm">
                        <Pencil class="size-4" />
                        {{ $t('Editar') }}
                    </Button>
                </Link>
            </div>

            <div class="absolute inset-x-0 bottom-0 space-y-3 p-5 md:p-7">
                <div class="flex flex-wrap items-center gap-2">
                    <TourStatusBadge :status="props.tour.status" />
                    <Badge
                        v-if="props.tour.category"
                        variant="secondary"
                        class="bg-white/15 text-white backdrop-blur-sm"
                    >
                        {{ categoryLabel(props.tour.category.name) }}
                    </Badge>
                </div>

                <h1
                    class="max-w-3xl text-2xl font-bold tracking-tight text-white md:text-4xl"
                >
                    {{ props.tour.name }}
                </h1>

                <div
                    class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-sm text-white/90"
                >
                    <span class="flex items-center gap-1.5">
                        <CalendarClock class="size-4" />
                        {{
                            $t(':hours h', { hours: props.tour.duration_hours })
                        }}
                    </span>
                    <span class="flex items-center gap-1.5">
                        <Gauge class="size-4" />
                        {{ difficultyLabel }}
                    </span>
                    <span class="flex items-center gap-1.5">
                        <Users class="size-4" />
                        {{
                            $t(':count cupos por salida', {
                                count: props.tour.default_capacity,
                            })
                        }}
                    </span>
                    <span class="font-semibold text-white">
                        {{
                            $t(':price por persona', {
                                price: formatCurrency(
                                    props.tour.base_price,
                                    currency,
                                ),
                            })
                        }}
                    </span>
                    <span
                        v-if="props.tour.rating_count > 0"
                        class="flex items-center gap-1.5"
                    >
                        <Star class="size-4 fill-white/90" />
                        {{
                            $t(':rating (:count reseñas)', {
                                rating: ratingValue.toFixed(1),
                                count: props.tour.rating_count,
                            })
                        }}
                    </span>
                </div>
            </div>
        </section>

        <TourTabs
            class="mt-6"
            :tabs="tabs"
            :model-value="activeTab"
            :label="$t('Secciones del tour')"
            @update:model-value="selectTab"
        />

        <!-- Resumen -->
        <div
            v-show="activeTab === 'summary'"
            :id="tourTabPanelId('summary')"
            role="tabpanel"
            :aria-labelledby="tourTabId('summary')"
            tabindex="0"
        >
            <section
                class="mt-6 grid grid-cols-1 gap-3.5 sm:grid-cols-2 xl:grid-cols-4"
            >
                <KpiCard
                    v-for="kpi in kpis"
                    :key="kpi.key"
                    :label="kpi.label"
                    :value="kpi.value"
                    :detail="kpi.detail"
                    :alert="kpi.alert"
                    :loading="kpi.loading"
                >
                    <template v-if="kpi.key === 'rating'" #suffix>
                        <span class="flex">
                            <Star
                                v-for="(filled, index) in stars"
                                :key="index"
                                class="size-4"
                                :class="
                                    filled
                                        ? 'fill-primary text-primary'
                                        : 'text-muted-foreground/30'
                                "
                            />
                        </span>
                    </template>
                </KpiCard>
            </section>

            <div
                class="mt-6 grid grid-cols-1 items-start gap-6 min-[1180px]:grid-cols-2"
            >
                <!-- Ocupación de las próximas salidas -->
                <section
                    class="rounded-2xl border border-border bg-card p-5 md:p-6"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-foreground">
                                {{ $t('Ocupación de las próximas salidas') }}
                            </h2>
                            <p
                                v-if="props.stats.upcoming_dates_count > 0"
                                class="mt-1 text-sm text-muted-foreground"
                            >
                                {{
                                    $t(
                                        ':booked de :capacity cupos vendidos en :count salidas próximas',
                                        {
                                            booked: occupancy.booked_total,
                                            capacity: occupancy.capacity_total,
                                            count: props.stats
                                                .upcoming_dates_count,
                                        },
                                    )
                                }}
                            </p>
                            <p
                                v-if="props.stats.next_date_starts_at"
                                class="mt-0.5 text-xs text-muted-foreground"
                            >
                                {{
                                    $t('Próxima el :date', {
                                        date: formatTourDate(
                                            props.stats.next_date_starts_at,
                                            { withTime: false },
                                        ),
                                    })
                                }}
                            </p>
                        </div>
                        <MonoLabel
                            v-if="props.stats.upcoming_dates_count > 0"
                            class="shrink-0 pt-1 text-foreground"
                        >
                            {{ $t(':percent %', { percent: occupancy.rate }) }}
                        </MonoLabel>
                    </div>

                    <template v-if="props.stats.upcoming_dates_count > 0">
                        <OccupancyBar
                            class="mt-4"
                            :occupied="occupancy.booked_total"
                            :capacity="occupancy.capacity_total"
                            hide-value
                        />

                        <div class="mt-5">
                            <TourUpcomingDatesList
                                :tour-id="props.tour.id"
                                :currency="currency"
                                :can-view-passengers="canViewPassengers"
                                @view-passengers="activeTab = 'passengers'"
                            />
                        </div>
                    </template>

                    <div
                        v-else
                        class="mt-4 flex flex-col items-center gap-3 rounded-xl border border-dashed border-border p-8 text-center"
                    >
                        <CalendarPlus class="size-8 text-muted-foreground/40" />
                        <div class="space-y-1">
                            <p class="font-medium text-foreground">
                                {{ $t('Sin salidas programadas') }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{
                                    $t(
                                        'Programa una salida para empezar a recibir reservas.',
                                    )
                                }}
                            </p>
                        </div>
                        <Link :href="editPage({ tour: props.tour.id }).url">
                            <Button size="sm">
                                <CalendarPlus class="size-4" />
                                {{ $t('Programar salida') }}
                            </Button>
                        </Link>
                    </div>
                </section>

                <!-- Itinerario -->
                <section
                    class="rounded-2xl border border-border bg-card p-5 md:p-6"
                >
                    <h2 class="text-base font-semibold text-foreground">
                        {{ $t('Itinerario') }}
                    </h2>

                    <p
                        v-if="props.tour.itinerary.length === 0"
                        class="mt-4 text-sm text-muted-foreground"
                    >
                        {{ $t('Este tour todavía no tiene itinerario.') }}
                    </p>

                    <ol v-else class="mt-5 space-y-5">
                        <li
                            v-for="(step, index) in props.tour.itinerary"
                            :key="step.step_number"
                            class="flex gap-4"
                        >
                            <div class="flex flex-col items-center">
                                <span
                                    class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground"
                                >
                                    {{ step.step_number }}
                                </span>
                                <span
                                    v-if="
                                        index < props.tour.itinerary.length - 1
                                    "
                                    class="mt-1 w-px flex-1 bg-brand-line-2"
                                />
                            </div>
                            <div class="min-w-0 pb-1">
                                <div
                                    class="flex flex-wrap items-baseline gap-x-2"
                                >
                                    <p class="font-medium text-foreground">
                                        {{ step.title }}
                                    </p>
                                    <span
                                        v-if="step.duration_label"
                                        class="text-xs text-muted-foreground"
                                    >
                                        · {{ step.duration_label }}
                                    </span>
                                </div>
                                <p
                                    v-if="step.description"
                                    class="mt-1 text-sm text-muted-foreground"
                                >
                                    {{ step.description }}
                                </p>
                            </div>
                        </li>
                    </ol>
                </section>
            </div>

            <!-- Galería -->
            <section
                v-if="sortedImages.length > 0"
                class="mt-6 rounded-2xl border border-border bg-card p-5 md:p-6"
            >
                <h2 class="mb-4 text-base font-semibold text-foreground">
                    {{ $t('Galería') }}
                </h2>
                <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <div
                        v-if="coverImage"
                        class="col-span-2 row-span-2 overflow-hidden rounded-xl md:col-span-2"
                    >
                        <img
                            :src="coverImage.url"
                            :alt="coverImage.alt_text ?? props.tour.name"
                            class="aspect-square size-full object-cover transition duration-300 hover:scale-105"
                        />
                    </div>
                    <div
                        v-for="image in galleryImages"
                        :key="image.id"
                        class="overflow-hidden rounded-xl"
                    >
                        <img
                            :src="image.url"
                            :alt="image.alt_text ?? props.tour.name"
                            class="aspect-square size-full object-cover transition duration-300 hover:scale-105"
                        />
                    </div>
                </div>
            </section>

            <!-- Descripción -->
            <section
                v-if="props.tour.description"
                class="mt-6 rounded-2xl border border-border bg-card p-5 md:p-6"
            >
                <h2 class="mb-3 text-base font-semibold text-foreground">
                    {{ $t('Descripción') }}
                </h2>
                <p class="text-sm whitespace-pre-line text-muted-foreground">
                    {{ props.tour.description }}
                </p>
            </section>

            <!-- Detalle -->
            <section class="mt-6 grid gap-4 md:grid-cols-3">
                <div class="rounded-2xl border border-border bg-card p-5">
                    <h3 class="mb-3 text-sm font-semibold text-foreground">
                        {{ $t('Incluye') }}
                    </h3>
                    <ul
                        v-if="props.tour.includes.length > 0"
                        class="space-y-2 text-sm"
                    >
                        <li
                            v-for="(item, index) in props.tour.includes"
                            :key="index"
                            class="flex items-start gap-2"
                        >
                            <Check
                                class="mt-0.5 size-4 shrink-0 text-primary"
                            />
                            <span class="text-foreground">{{ item }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">
                        {{ $t('Sin detalle.') }}
                    </p>
                </div>

                <div class="rounded-2xl border border-border bg-card p-5">
                    <h3 class="mb-3 text-sm font-semibold text-foreground">
                        {{ $t('No incluye') }}
                    </h3>
                    <ul
                        v-if="props.tour.excludes.length > 0"
                        class="space-y-2 text-sm"
                    >
                        <li
                            v-for="(item, index) in props.tour.excludes"
                            :key="index"
                            class="flex items-start gap-2"
                        >
                            <X
                                class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                            />
                            <span class="text-muted-foreground">{{
                                item
                            }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">
                        {{ $t('Sin detalle.') }}
                    </p>
                </div>

                <div class="rounded-2xl border border-border bg-card p-5">
                    <h3 class="mb-3 text-sm font-semibold text-foreground">
                        {{ $t('Requisitos') }}
                    </h3>
                    <ul
                        v-if="props.tour.requirements.length > 0"
                        class="space-y-2 text-sm"
                    >
                        <li
                            v-for="(item, index) in props.tour.requirements"
                            :key="index"
                            class="flex items-start gap-2"
                        >
                            <span
                                class="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary"
                            />
                            <span class="text-foreground">{{ item }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">
                        {{ $t('Sin detalle.') }}
                    </p>
                </div>
            </section>
        </div>

        <!--
          `v-if`, no `v-show`: la planilla dispara su fetch al montarse. Con
          `v-show` cada visita al detalle pediría los pasajeros aunque nadie
          abra la pestaña.
        -->
        <section
            v-if="activeTab === 'passengers'"
            :id="tourTabPanelId('passengers')"
            role="tabpanel"
            :aria-labelledby="tourTabId('passengers')"
            tabindex="0"
            class="mt-6"
        >
            <PassengerManifest
                :source="manifestSource"
                :title="props.tour.name"
            />
        </section>

        <!--
          `v-show`, no `v-if`: el mapa se monta una vez y `fit()` lo re-mide al
          volver a la pestaña. Con `v-if` se recrearía Leaflet en cada visita.
        -->
        <section
            v-show="activeTab === 'route'"
            :id="tourTabPanelId('route')"
            role="tabpanel"
            :aria-labelledby="tourTabId('route')"
            tabindex="0"
            class="mt-6 rounded-2xl border border-border bg-card p-5 md:p-6"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-foreground">
                        {{ $t('Ruta publicada') }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            $t(
                                'Así ve el viajero la recogida, el recorrido y el punto de regreso.',
                            )
                        }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a
                        v-if="mapsUrl"
                        :href="mapsUrl"
                        target="_blank"
                        rel="noopener"
                    >
                        <Button variant="outline" size="sm">
                            <MapPin class="size-4" />
                            {{ $t('Ver en Google Maps') }}
                        </Button>
                    </a>
                    <Link :href="editPage({ tour: props.tour.id }).url">
                        <Button variant="outline" size="sm">
                            <Pencil class="size-4" />
                            {{ $t('Editar paradas') }}
                        </Button>
                    </Link>
                </div>
            </div>

            <p
                v-if="props.tour.meeting_point"
                class="mt-4 flex items-start gap-2 text-sm text-muted-foreground"
            >
                <MapPin class="mt-0.5 size-4 shrink-0 text-primary" />
                <span>
                    <span class="font-medium text-foreground">
                        {{ $t('Punto de encuentro') }}:
                    </span>
                    {{ props.tour.meeting_point }}
                </span>
            </p>

            <div class="mt-5">
                <TourRouteMapSection ref="mapSection" :stops="routeStops" />
            </div>
        </section>
    </div>
</template>
