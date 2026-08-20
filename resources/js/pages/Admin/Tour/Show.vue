<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarClock,
    CalendarPlus,
    Check,
    DollarSign,
    ExternalLink,
    Gauge,
    MapPin,
    Mountain,
    Pencil,
    Star,
    TicketCheck,
    Users,
    X,
} from 'lucide-vue-next';
import { computed } from 'vue';
import {
    edit as editPage,
    index as toursIndex,
} from '@/actions/App/Http/Controllers/Admin/TourPagesController';
import TourStatusBadge from '@/components/organisms/TourStatusBadge.vue';
import TourUpcomingDatesList from '@/components/organisms/TourUpcomingDatesList.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTenant } from '@/composables/useTenant';
import { useTranslations } from '@/composables/useTranslations';
import { formatCurrency, formatTourDate } from '@/lib/format';
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
</script>

<template>
    <Head :title="props.tour.name" />

    <div class="px-4 py-6 md:px-8">
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
                        {{ props.tour.category.name }}
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
                        {{ props.tour.duration_hours }}h
                    </span>
                    <span class="flex items-center gap-1.5">
                        <Gauge class="size-4" />
                        {{ difficultyLabel }}
                    </span>
                    <span class="flex items-center gap-1.5">
                        <Users class="size-4" />
                        {{ props.tour.default_capacity }} personas
                    </span>
                    <span
                        class="flex items-center gap-1.5 font-semibold text-white"
                    >
                        <DollarSign class="size-4" />
                        {{ formatCurrency(props.tour.base_price, currency) }}
                    </span>
                </div>
            </div>
        </section>

        <!-- KPIs -->
        <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-border bg-card p-5">
                <div
                    class="flex items-center justify-between text-sm text-muted-foreground"
                >
                    <span>{{ $t('Reservas') }}</span>
                    <TicketCheck class="size-4" />
                </div>
                <p
                    class="mt-2 text-3xl font-semibold tracking-tight tabular-nums"
                >
                    {{ props.stats.bookings.total }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{
                        $t(
                            ':confirmed confirmadas · :pending pendientes · :cancelled canceladas',
                            {
                                confirmed: props.stats.bookings.confirmed,
                                pending: props.stats.bookings.pending_payment,
                                cancelled: props.stats.bookings.cancelled,
                            },
                        )
                    }}
                </p>
            </div>

            <div class="rounded-2xl border border-border bg-card p-5">
                <div
                    class="flex items-center justify-between text-sm text-muted-foreground"
                >
                    <span>{{ $t('Viajeros') }}</span>
                    <Users class="size-4" />
                </div>
                <p
                    class="mt-2 text-3xl font-semibold tracking-tight tabular-nums"
                >
                    {{ props.stats.travelers_total }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ $t('En reservas confirmadas y completadas') }}
                </p>
            </div>

            <div class="rounded-2xl border border-border bg-card p-5">
                <div
                    class="flex items-center justify-between text-sm text-muted-foreground"
                >
                    <span>{{ $t('Ingresos') }}</span>
                    <DollarSign class="size-4" />
                </div>
                <p
                    class="mt-2 text-3xl font-semibold tracking-tight tabular-nums"
                >
                    {{
                        formatCurrency(
                            props.stats.revenue_total,
                            props.stats.currency,
                        )
                    }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ $t('Pagos completados') }}
                </p>
            </div>

            <div class="rounded-2xl border border-border bg-card p-5">
                <div
                    class="flex items-center justify-between text-sm text-muted-foreground"
                >
                    <span>{{ $t('Calificación') }}</span>
                    <Star class="size-4" />
                </div>
                <div class="mt-2 flex items-center gap-2">
                    <p
                        class="text-3xl font-semibold tracking-tight tabular-nums"
                    >
                        {{ ratingValue.toFixed(1) }}
                    </p>
                    <div class="flex">
                        <Star
                            v-for="(filled, index) in stars"
                            :key="index"
                            class="size-4"
                            :class="
                                filled
                                    ? 'fill-amber-400 text-amber-400'
                                    : 'text-muted-foreground/30'
                            "
                        />
                    </div>
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{
                        $t(':count reseñas', { count: props.tour.rating_count })
                    }}
                </p>
            </div>
        </section>

        <!-- Occupancy -->
        <section
            class="mt-6 rounded-2xl border border-border bg-card p-5 md:p-6"
        >
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-foreground">
                    {{ $t('Ocupación próxima') }}
                </h2>
                <span
                    v-if="props.stats.upcoming_dates_count > 0"
                    class="text-sm font-semibold text-foreground tabular-nums"
                >
                    {{ occupancy.rate }}%
                </span>
            </div>

            <template v-if="props.stats.upcoming_dates_count > 0">
                <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-muted">
                    <div
                        class="h-full rounded-full bg-primary transition-all"
                        :style="{ width: `${occupancy.rate}%` }"
                    />
                </div>
                <div
                    class="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm text-muted-foreground"
                >
                    <span>
                        {{
                            $t(':booked / :capacity cupos reservados', {
                                booked: occupancy.booked_total,
                                capacity: occupancy.capacity_total,
                            })
                        }}
                    </span>
                    <span>
                        {{
                            $t(':count salidas próximas', {
                                count: props.stats.upcoming_dates_count,
                            })
                        }}
                        <template v-if="props.stats.next_date_starts_at">
                            {{
                                $t('· próxima el :date', {
                                    date: formatTourDate(
                                        props.stats.next_date_starts_at,
                                        { withTime: false },
                                    ),
                                })
                            }}
                        </template>
                    </span>
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

        <div class="mt-6 grid gap-6 lg:grid-cols-5">
            <!-- Upcoming dates -->
            <section class="lg:col-span-3">
                <div
                    class="h-full rounded-2xl border border-border bg-card p-5 md:p-6"
                >
                    <h2 class="mb-4 text-base font-semibold text-foreground">
                        {{ $t('Salidas próximas') }}
                    </h2>
                    <TourUpcomingDatesList
                        :tour-id="props.tour.id"
                        :currency="currency"
                    />
                </div>
            </section>

            <!-- Itinerary -->
            <section class="lg:col-span-2">
                <div
                    class="h-full rounded-2xl border border-border bg-card p-5 md:p-6"
                >
                    <h2 class="mb-4 text-base font-semibold text-foreground">
                        {{ $t('Itinerario') }}
                    </h2>

                    <p
                        v-if="props.tour.itinerary.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        {{ $t('Este tour todavía no tiene itinerario.') }}
                    </p>

                    <ol v-else class="relative space-y-6">
                        <li
                            v-for="(step, index) in props.tour.itinerary"
                            :key="step.id"
                            class="relative flex gap-4"
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
                                    class="mt-1 w-px flex-1 bg-border"
                                />
                            </div>
                            <div class="pb-1">
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
                                        {{ step.duration_label }}
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
                </div>
            </section>
        </div>

        <!-- Gallery -->
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

        <!-- Description -->
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

        <!-- Details -->
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
                            class="mt-0.5 size-4 shrink-0 text-emerald-600"
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
                        <span class="text-muted-foreground">{{ item }}</span>
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

        <!-- Meeting point -->
        <section
            v-if="props.tour.meeting_point"
            class="mt-6 rounded-2xl border border-border bg-card p-5 md:p-6"
        >
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <MapPin class="mt-0.5 size-5 shrink-0 text-primary" />
                    <div>
                        <h3 class="text-sm font-semibold text-foreground">
                            {{ $t('Punto de encuentro') }}
                        </h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ props.tour.meeting_point }}
                        </p>
                    </div>
                </div>
                <a
                    v-if="mapsUrl"
                    :href="mapsUrl"
                    target="_blank"
                    rel="noopener"
                >
                    <Button variant="outline" size="sm">
                        <MapPin class="size-4" />
                        {{ $t('Ver en el mapa') }}
                    </Button>
                </a>
            </div>
        </section>
    </div>
</template>
