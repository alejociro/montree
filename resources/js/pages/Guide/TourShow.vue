<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    CalendarClock,
    Check,
    Gauge,
    MapPin,
    Mountain,
    RefreshCw,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { show as guideTourApi } from '@/actions/App/Http/Controllers/Api/V1/Guide/GuideTourController';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import PassengerManifest from '@/components/organisms/PassengerManifest.vue';
import TourRouteMapSection from '@/components/organisms/TourRouteMapSection.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useTranslations } from '@/composables/useTranslations';
import { formatTourDate } from '@/lib/format';
import { passengers as guidePassengersRoute } from '@/routes/guide';
import { schedule as scheduleRoute } from '@/routes/guide';
import type { TourDifficulty } from '@/types/tour';
import type { TourRouteStop } from '@/types/tour-route';

const { t } = useTranslations();

const props = defineProps<{ tourId: number }>();

type GuideDeparture = {
    id: number;
    starts_at: string;
    ends_at: string | null;
    capacity: number;
    booked_count: number;
    status: string;
    passengers_count: number;
};

type GuideTour = {
    id: number;
    slug: string;
    name: string;
    summary: string | null;
    description: string | null;
    duration_hours: number;
    difficulty: TourDifficulty;
    category: { id: number; name: string } | null;
    cover_image_url: string | null;
    images: Array<{
        id: number;
        url: string;
        is_cover: boolean;
        alt_text: string | null;
    }>;
    includes: string[];
    excludes: string[];
    requirements: string[];
    itinerary: Array<{
        step_number: number;
        title: string;
        description: string | null;
        duration_label: string | null;
    }>;
    stops: TourRouteStop[];
    meeting_point: string | null;
    meeting_latitude: number | null;
    meeting_longitude: number | null;
    my_departures: GuideDeparture[];
};

const tour = ref<GuideTour | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);

const difficultyLabels: Record<TourDifficulty, string> = {
    easy: t('Fácil'),
    moderate: t('Moderado'),
    hard: t('Difícil'),
    extreme: t('Extremo'),
};

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        const response = await fetch(guideTourApi.url(props.tourId), {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        const payload = (await response.json()) as { data: GuideTour };
        tour.value = payload.data;
    } catch {
        error.value = t('No pudimos cargar el tour.');
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    void load();
});

/**
 * La salida que abre la planilla: la próxima que todavía no empezó, o la última
 * si ya pasaron todas. El guía llega a esta pantalla el día antes del tour.
 */
const activeDeparture = computed<GuideDeparture | null>(() => {
    const departures = tour.value?.my_departures ?? [];

    if (departures.length === 0) {
        return null;
    }

    const now = Date.now();

    return (
        departures.find(
            (departure) => new Date(departure.starts_at).getTime() >= now,
        ) ??
        departures[departures.length - 1] ??
        null
    );
});

const manifestSource = computed(() => {
    const departure = activeDeparture.value;

    return departure === null
        ? null
        : ({ kind: 'departure', tourDateId: departure.id } as const);
});

const mapsUrl = computed(() => {
    const lat = tour.value?.meeting_latitude;
    const lng = tour.value?.meeting_longitude;

    if (!lat || !lng) {
        return null;
    }

    return `https://www.google.com/maps/search/?api=1&query=${lat},${lng}`;
});

function occupancy(departure: GuideDeparture): number {
    if (departure.capacity <= 0) {
        return 0;
    }

    return Math.min(
        100,
        Math.round((departure.booked_count / departure.capacity) * 100),
    );
}
</script>

<template>
    <div class="container mx-auto max-w-5xl space-y-6 px-4 py-8">
        <Head :title="tour?.name ?? $t('Tour')" />

        <div class="print:hidden">
            <Link
                :href="scheduleRoute().url"
                class="inline-flex items-center gap-1.5 text-sm text-muted-foreground transition hover:text-foreground"
            >
                <ArrowLeft class="size-4" />
                {{ $t('Volver a mi agenda') }}
            </Link>
        </div>

        <!-- Error -->
        <div
            v-if="error"
            class="flex flex-col items-center gap-3 rounded-xl border border-brand-drop/30 bg-brand-drop-50 p-10 text-center"
        >
            <AlertTriangle class="size-8 text-brand-drop" />
            <p class="text-sm font-medium text-brand-drop">{{ error }}</p>
            <Button variant="outline" size="sm" @click="load">
                <RefreshCw class="size-4" />
                {{ $t('Reintentar') }}
            </Button>
        </div>

        <!-- Loading -->
        <div v-else-if="loading" class="space-y-4">
            <Skeleton class="h-52 w-full rounded-2xl" />
            <Skeleton class="h-8 w-2/3 rounded-md" />
            <Skeleton class="h-40 w-full rounded-2xl" />
        </div>

        <template v-else-if="tour">
            <!-- Hero -->
            <section
                class="relative overflow-hidden rounded-2xl border border-border"
            >
                <div class="aspect-[21/9] w-full sm:aspect-[21/7]">
                    <img
                        v-if="tour.cover_image_url"
                        :src="tour.cover_image_url"
                        :alt="tour.name"
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
                <div class="absolute inset-x-0 bottom-0 space-y-3 p-5 md:p-7">
                    <Badge
                        v-if="tour.category"
                        variant="secondary"
                        class="bg-white/15 text-white backdrop-blur-sm"
                    >
                        {{ tour.category.name }}
                    </Badge>
                    <h1
                        class="max-w-3xl text-2xl font-bold tracking-tight text-white md:text-4xl"
                    >
                        {{ tour.name }}
                    </h1>
                    <div
                        class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-sm text-white/90"
                    >
                        <span class="flex items-center gap-1.5">
                            <CalendarClock class="size-4" />
                            {{ $t(':count h', { count: tour.duration_hours }) }}
                        </span>
                        <span class="flex items-center gap-1.5">
                            <Gauge class="size-4" />
                            {{
                                difficultyLabels[tour.difficulty] ??
                                tour.difficulty
                            }}
                        </span>
                    </div>
                </div>
            </section>

            <p v-if="tour.summary" class="text-base text-muted-foreground">
                {{ tour.summary }}
            </p>

            <!-- Mis salidas -->
            <section class="rounded-2xl border border-border bg-card p-5">
                <h2 class="mb-4 text-base font-semibold text-foreground">
                    {{ $t('Mis salidas') }}
                </h2>
                <ul class="space-y-2">
                    <li
                        v-for="departure in tour.my_departures"
                        :key="departure.id"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border p-3"
                    >
                        <div>
                            <p class="text-sm font-medium text-foreground">
                                {{ formatTourDate(departure.starts_at) }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{
                                    $t(':booked / :capacity cupos reservados', {
                                        booked: departure.booked_count,
                                        capacity: departure.capacity,
                                    })
                                }}
                            </p>
                            <div
                                class="mt-1.5 h-1.5 w-40 overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full bg-primary"
                                    :style="{
                                        width: `${occupancy(departure)}%`,
                                    }"
                                />
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span
                                class="flex items-center gap-1.5 text-sm text-muted-foreground"
                            >
                                <Users class="size-4" />
                                {{ departure.passengers_count }}
                            </span>
                            <Link
                                :href="guidePassengersRoute(departure.id).url"
                            >
                                <Button variant="outline" size="sm">
                                    {{ $t('Ver planilla') }}
                                </Button>
                            </Link>
                        </div>
                    </li>
                </ul>
            </section>

            <!-- Ruta y mapa (reutiliza el componente del PR #15) -->
            <section
                v-if="tour.stops.length > 0"
                class="rounded-2xl border border-border bg-card p-5"
            >
                <TourRouteMapSection :stops="tour.stops" />
            </section>

            <!-- Itinerario -->
            <section
                v-if="tour.itinerary.length > 0"
                class="rounded-2xl border border-border bg-card p-5"
            >
                <h2 class="mb-4 text-base font-semibold text-foreground">
                    {{ $t('Itinerario') }}
                </h2>
                <ol class="space-y-6">
                    <li
                        v-for="(step, index) in tour.itinerary"
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
                                v-if="index < tour.itinerary.length - 1"
                                class="mt-1 w-px flex-1 bg-border"
                            />
                        </div>
                        <div class="pb-1">
                            <div class="flex flex-wrap items-baseline gap-x-2">
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
            </section>

            <!-- Logística -->
            <section class="grid gap-4 md:grid-cols-3">
                <div class="rounded-2xl border border-border bg-card p-5">
                    <MonoLabel class="mb-3">{{ $t('Incluye') }}</MonoLabel>
                    <ul
                        v-if="tour.includes.length > 0"
                        class="space-y-2 text-sm"
                    >
                        <li
                            v-for="(item, index) in tour.includes"
                            :key="index"
                            class="flex items-start gap-2"
                        >
                            <Check
                                class="mt-0.5 size-4 shrink-0 text-brand-green-600"
                            />
                            <span>{{ item }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">
                        {{ $t('Sin detalle.') }}
                    </p>
                </div>

                <div class="rounded-2xl border border-border bg-card p-5">
                    <MonoLabel class="mb-3">{{ $t('No incluye') }}</MonoLabel>
                    <ul
                        v-if="tour.excludes.length > 0"
                        class="space-y-2 text-sm"
                    >
                        <li
                            v-for="(item, index) in tour.excludes"
                            :key="index"
                            class="flex items-start gap-2"
                        >
                            <X
                                class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                            />
                            <span class="text-muted-foreground">
                                {{ item }}
                            </span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">
                        {{ $t('Sin detalle.') }}
                    </p>
                </div>

                <div class="rounded-2xl border border-border bg-card p-5">
                    <MonoLabel class="mb-3">{{ $t('Requisitos') }}</MonoLabel>
                    <ul
                        v-if="tour.requirements.length > 0"
                        class="space-y-2 text-sm"
                    >
                        <li
                            v-for="(item, index) in tour.requirements"
                            :key="index"
                            class="flex items-start gap-2"
                        >
                            <span
                                class="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary"
                            />
                            <span>{{ item }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">
                        {{ $t('Sin detalle.') }}
                    </p>
                </div>
            </section>

            <!-- Punto de encuentro -->
            <section
                v-if="tour.meeting_point"
                class="rounded-2xl border border-border bg-card p-5"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <MapPin class="mt-0.5 size-5 shrink-0 text-primary" />
                        <div>
                            <MonoLabel>
                                {{ $t('Punto de encuentro') }}
                            </MonoLabel>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ tour.meeting_point }}
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

            <!-- Descripción -->
            <section
                v-if="tour.description"
                class="rounded-2xl border border-border bg-card p-5"
            >
                <MonoLabel class="mb-3">{{ $t('Descripción') }}</MonoLabel>
                <p class="text-sm whitespace-pre-line text-muted-foreground">
                    {{ tour.description }}
                </p>
            </section>

            <!--
              Planilla de la salida activa, en lectura. `readonly` porque el
              guía no crea, no edita y no registra pagos (D1).
            -->
            <section
                v-if="manifestSource && activeDeparture"
                class="rounded-2xl border border-border bg-card p-5"
            >
                <PassengerManifest
                    :key="activeDeparture.id"
                    :source="manifestSource"
                    readonly
                    :title="
                        $t('Pasajeros del :date', {
                            date: formatTourDate(activeDeparture.starts_at, {
                                withWeekday: false,
                                withTime: false,
                            }),
                        })
                    "
                />
            </section>
        </template>
    </div>
</template>
