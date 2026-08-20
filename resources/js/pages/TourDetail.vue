<script setup lang="ts">
import { Deferred, Head, Link, usePage } from '@inertiajs/vue3';
import {
    CalendarOff,
    ChevronLeft,
    Clock,
    MapPin,
    Mountain,
    Star,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { index as tourReviewsIndex } from '@/actions/App/Http/Controllers/Api/V1/PublicReviewController';
import FavoriteButton from '@/components/molecules/FavoriteButton.vue';
import RatingBreakdown from '@/components/molecules/RatingBreakdown.vue';
import ReviewCard from '@/components/molecules/ReviewCard.vue';
import TourRouteMapSection from '@/components/organisms/TourRouteMapSection.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { formatTourDate } from '@/lib/format';
import { routeStopsFromTour } from '@/lib/tour-route';
import { index as catalogIndex } from '@/routes/catalog';
import { show as tourShow } from '@/routes/tours';
import type {
    ReviewSummary,
    TourDetail,
    TourDetailDate,
    TourDetailImage,
} from '@/types/tour-detail';

const { t } = useTranslations();

defineOptions({ layout: PublicLayout });

interface CatalogTour {
    id: number;
    slug: string;
    name: string;
    short_description: string | null;
    base_price: string;
    currency: string;
    duration_hours: number;
    difficulty: string;
    category: { id: number; name: string; slug: string } | null;
    cover_image_url: string | null;
    rating_average: string;
    rating_count: number;
}

const props = defineProps<{
    tour: TourDetail;
    relatedTours?: CatalogTour[];
}>();

const page = usePage();
const isAuthenticated = computed(() => page.props.auth?.user != null);

const routeStops = computed(() => routeStopsFromTour(props.tour));

const routeNote = computed(() =>
    props.tour.meeting_point === null
        ? null
        : t('Punto de encuentro: :place.', { place: props.tour.meeting_point }),
);

const difficultyLabel = computed(() => {
    const map: Record<string, string> = {
        easy: t('Fácil'),
        moderate: 'Moderado',
        hard: t('Difícil'),
        extreme: 'Extremo',
    };

    return map[props.tour.difficulty] ?? props.tour.difficulty;
});

const reviews = ref<ReviewSummary[]>([]);
const reviewsLoading = ref(true);
const reviewsLoadError = ref(false);
const reviewsLastPage = ref(1);
const reviewsPage = ref(1);
const loadingMoreReviews = ref(false);

const hasMoreReviews = computed(
    () => reviewsPage.value < reviewsLastPage.value,
);

type ReviewsPageResponse = {
    data: ReviewSummary[];
    meta: { current_page: number; last_page: number };
};

async function loadReviews(pageNumber: number): Promise<void> {
    const isFirstPage = pageNumber === 1;

    if (isFirstPage) {
        reviewsLoading.value = true;
        reviewsLoadError.value = false;
    } else {
        loadingMoreReviews.value = true;
    }

    try {
        const response = await fetch(
            tourReviewsIndex.url(props.tour.slug, {
                query: { page: pageNumber },
            }),
            {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            },
        );

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const json = (await response.json()) as ReviewsPageResponse;
        reviews.value = isFirstPage
            ? json.data
            : [...reviews.value, ...json.data];
        reviewsPage.value = json.meta.current_page;
        reviewsLastPage.value = json.meta.last_page;
    } catch {
        if (isFirstPage) {
            reviewsLoadError.value = true;
        }
    } finally {
        reviewsLoading.value = false;
        loadingMoreReviews.value = false;
    }
}

onMounted(() => {
    if (props.tour.rating_count > 0) {
        void loadReviews(1);
    } else {
        reviewsLoading.value = false;
    }
});

const activeImageIndex = ref(0);
const lightboxOpen = ref(false);

const activeImage = computed<TourDetailImage | null>(
    () => props.tour.images[activeImageIndex.value] ?? null,
);

function openLightbox(index: number) {
    activeImageIndex.value = index;
    lightboxOpen.value = true;
}

function closeLightbox() {
    lightboxOpen.value = false;
}

function nextImage() {
    activeImageIndex.value =
        (activeImageIndex.value + 1) % props.tour.images.length;
}

function prevImage() {
    activeImageIndex.value =
        (activeImageIndex.value - 1 + props.tour.images.length) %
        props.tour.images.length;
}

function formatTourPrice(price: string, currency: string): string {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency,
        maximumFractionDigits: 0,
    }).format(Number(price));
}

const selectableDates = computed<TourDetailDate[]>(() =>
    props.tour.future_dates.filter(
        (date) => !date.is_full && date.status === 'open',
    ),
);

const selectedDateId = ref<number | null>(null);

const selectedDate = computed<TourDetailDate | null>(
    () =>
        props.tour.future_dates.find(
            (date) => date.id === selectedDateId.value,
        ) ?? null,
);

const bookingUrl = computed(() =>
    selectedDate.value
        ? `/booking/new?tour_date_id=${selectedDate.value.id}`
        : null,
);

function dateOptionLabel(date: TourDetailDate): string {
    const label = formatTourDate(date.starts_at, {
        withWeekday: true,
        withTime: true,
    });
    const price = formatTourPrice(date.effective_price, props.tour.currency);

    return `${label} · ${date.available_seats} cupos · ${price}`;
}
</script>

<template>
    <Head :title="tour.name" />

    <!-- Breadcrumb -->
    <div class="mx-auto w-full max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
        <nav class="flex items-center gap-2 text-sm text-muted-foreground">
            <Link
                :href="catalogIndex().url"
                class="transition hover:text-foreground"
            >
                {{ $t('Tours') }}
            </Link>
            <ChevronLeft class="size-3.5 rotate-180" />
            <span v-if="tour.category" class="transition hover:text-foreground">
                {{ tour.category.name }}
            </span>
            <ChevronLeft v-if="tour.category" class="size-3.5 rotate-180" />
            <span class="truncate text-foreground">{{ tour.name }}</span>
        </nav>
    </div>

    <!-- Main 2-column layout -->
    <div class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <div class="grid gap-8 lg:grid-cols-2">
            <!-- LEFT: Image gallery -->
            <div class="space-y-3">
                <div class="relative">
                    <div
                        class="aspect-[4/3] w-full cursor-pointer overflow-hidden rounded-2xl bg-muted"
                        @click="openLightbox(activeImageIndex)"
                    >
                        <img
                            v-if="activeImage?.url"
                            :src="activeImage.url"
                            :alt="activeImage.alt_text ?? tour.name"
                            class="h-full w-full object-cover transition-transform duration-300 hover:scale-105"
                        />
                    </div>
                    <FavoriteButton
                        v-if="isAuthenticated"
                        :tour-id="tour.id"
                        :initial-favorite="tour.is_favorite"
                        class="absolute top-3 right-3"
                    />
                </div>

                <!-- Thumbnails -->
                <div
                    v-if="tour.images.length > 1"
                    class="flex snap-x snap-mandatory gap-2 overflow-x-auto pb-1"
                >
                    <button
                        v-for="(img, i) in tour.images"
                        :key="img.id"
                        type="button"
                        class="aspect-square w-20 flex-none snap-start overflow-hidden rounded-lg border-2 transition"
                        :class="
                            i === activeImageIndex
                                ? 'border-primary'
                                : 'border-transparent opacity-70 hover:opacity-100'
                        "
                        @click="activeImageIndex = i"
                    >
                        <img
                            v-if="img.url"
                            :src="img.url"
                            :alt="img.alt_text ?? tour.name"
                            class="h-full w-full object-cover"
                        />
                    </button>
                </div>

                <!-- Includes -->
                <div v-if="tour.includes.length > 0" class="space-y-3 pt-2">
                    <h2 class="text-base font-semibold">
                        {{ $t('¿Qué incluye?') }}
                    </h2>
                    <ul
                        class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm text-muted-foreground"
                    >
                        <li
                            v-for="(item, i) in tour.includes"
                            :key="i"
                            class="flex items-start gap-2"
                        >
                            <span class="mt-0.5 shrink-0 text-primary">✓</span>
                            {{ item }}
                        </li>
                    </ul>
                </div>

                <!-- Requirements -->
                <div v-if="tour.requirements.length > 0" class="space-y-3">
                    <h2 class="text-base font-semibold">
                        {{ $t('Requisitos') }}
                    </h2>
                    <ul class="space-y-1.5 text-sm text-muted-foreground">
                        <li
                            v-for="(item, i) in tour.requirements"
                            :key="i"
                            class="flex items-start gap-2"
                        >
                            <span class="mt-0.5 shrink-0">•</span>
                            {{ item }}
                        </li>
                    </ul>
                </div>
            </div>

            <!-- RIGHT: Tour info -->
            <div class="space-y-6">
                <!-- Category badge -->
                <Badge v-if="tour.category" variant="secondary" class="text-xs">
                    {{ tour.category.name }}
                </Badge>

                <!-- Tour name -->
                <h1 class="text-2xl leading-tight font-bold sm:text-3xl">
                    {{ tour.name }}
                </h1>

                <!-- Quick stats -->
                <div
                    class="flex flex-wrap items-center gap-4 text-sm text-muted-foreground"
                >
                    <span class="flex items-center gap-1.5">
                        <Clock class="size-4" />
                        {{ tour.duration_hours }} horas
                    </span>
                    <span class="flex items-center gap-1.5">
                        <Mountain class="size-4" />
                        {{ difficultyLabel }}
                    </span>
                    <span class="flex items-center gap-1.5">
                        <Users class="size-4" />
                        {{
                            $t('Hasta :count personas', {
                                count: tour.default_capacity,
                            })
                        }}
                    </span>
                    <span
                        v-if="tour.rating_count > 0"
                        class="flex items-center gap-1"
                    >
                        <Star class="size-4 fill-amber-400 text-amber-400" />
                        {{ tour.rating_average }}
                        <span class="text-muted-foreground"
                            >({{ tour.rating_count }})</span
                        >
                    </span>
                </div>

                <!-- Description -->
                <div class="space-y-2">
                    <p
                        class="text-sm leading-relaxed whitespace-pre-line text-muted-foreground"
                    >
                        {{ tour.short_description ?? tour.description }}
                    </p>
                </div>

                <!-- Itinerary timeline -->
                <div v-if="tour.itinerary.length > 0" class="space-y-3">
                    <h2 class="text-lg font-semibold">
                        {{ $t('Itinerario') }}
                    </h2>
                    <div class="relative space-y-0 pl-6">
                        <div
                            class="absolute top-2 bottom-2 left-[9px] w-0.5 bg-primary/20"
                        />
                        <div
                            v-for="step in tour.itinerary"
                            :key="step.step_number"
                            class="relative pb-5 last:pb-0"
                        >
                            <div
                                class="absolute top-1 -left-6 flex size-5 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-primary-foreground"
                            >
                                {{ step.step_number }}
                            </div>
                            <div>
                                <p class="leading-tight font-medium">
                                    {{ step.title }}
                                </p>
                                <p
                                    v-if="step.duration_label"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ step.duration_label }}
                                </p>
                                <p
                                    v-if="step.description"
                                    class="mt-1 text-sm text-muted-foreground"
                                >
                                    {{ step.description }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Availability / booking -->
                <div class="space-y-3 rounded-2xl border bg-muted/30 p-5">
                    <h2 class="text-lg font-semibold">
                        {{ $t('Disponibilidad') }}
                    </h2>

                    <template v-if="selectableDates.length > 0">
                        <div class="space-y-1.5">
                            <label
                                for="tour-date-select"
                                class="text-sm font-medium text-foreground"
                            >
                                {{ $t('Selecciona una fecha') }}
                            </label>
                            <select
                                id="tour-date-select"
                                v-model="selectedDateId"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2.5 text-sm capitalize shadow-sm transition focus:border-primary focus:ring-2 focus:ring-primary/30 focus:outline-none"
                            >
                                <option :value="null" disabled>
                                    {{ $t('Seleccionar fecha') }}
                                </option>
                                <option
                                    v-for="date in selectableDates"
                                    :key="date.id"
                                    :value="date.id"
                                >
                                    {{ dateOptionLabel(date) }}
                                </option>
                            </select>
                        </div>

                        <!-- Selected date details -->
                        <div
                            v-if="selectedDate"
                            class="flex items-center justify-between rounded-lg border bg-background px-4 py-3"
                        >
                            <span
                                class="flex items-center gap-1.5 text-sm text-muted-foreground"
                            >
                                <Users class="size-4 text-primary" />
                                {{ selectedDate.available_seats }} cupos
                                disponibles
                            </span>
                            <span class="text-right">
                                <span class="block font-bold text-primary">{{
                                    formatTourPrice(
                                        selectedDate.effective_price,
                                        tour.currency,
                                    )
                                }}</span>
                                <span class="text-xs text-muted-foreground">{{
                                    $t('por persona')
                                }}</span>
                            </span>
                        </div>

                        <Button
                            v-if="bookingUrl"
                            as-child
                            size="lg"
                            class="w-full bg-primary text-primary-foreground hover:bg-primary/90"
                        >
                            <Link :href="bookingUrl">{{
                                $t('Reservar ahora')
                            }}</Link>
                        </Button>
                        <Button v-else size="lg" class="w-full" disabled>
                            {{ $t('Reservar ahora') }}
                        </Button>
                    </template>

                    <div
                        v-else
                        class="rounded-xl border border-dashed bg-background px-4 py-6 text-center"
                    >
                        <CalendarOff
                            class="mx-auto size-8 text-muted-foreground/40"
                        />
                        <p class="mt-3 font-medium text-foreground">
                            {{ $t('Sin fechas disponibles') }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{
                                tour.future_dates.length === 0
                                    ? 'Todavía no hay salidas programadas para esta experiencia. Vuelve pronto para reservar.'
                                    : 'Por ahora no quedan cupos abiertos. Vuelve pronto para nuevas salidas.'
                            }}
                        </p>
                    </div>
                </div>

                <!-- Meeting point: solo cuando no hay coordenadas para el mapa de ruta -->
                <div
                    v-if="routeStops.length === 0 && tour.meeting_point"
                    class="space-y-2"
                >
                    <h2 class="text-lg font-semibold">
                        {{ $t('Punto de encuentro') }}
                    </h2>
                    <div
                        class="flex items-start gap-2 text-sm text-muted-foreground"
                    >
                        <MapPin class="mt-0.5 size-4 shrink-0 text-primary" />
                        <p>{{ tour.meeting_point }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ruta y puntos de encuentro -->
    <div
        v-if="routeStops.length > 0"
        class="mx-auto w-full max-w-7xl px-4 pb-6 sm:px-6 lg:px-8"
    >
        <TourRouteMapSection :stops="routeStops" :note="routeNote" />
    </div>

    <!-- Reviews section -->
    <section class="border-t bg-muted/30">
        <div class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <h2 class="mb-8 text-2xl font-bold">
                {{ $t('Calificaciones y reseñas') }}
            </h2>

            <div v-if="tour.rating_count > 0" class="grid gap-8 lg:grid-cols-3">
                <div class="lg:col-span-1">
                    <div
                        class="sticky top-24 rounded-xl border bg-background p-6"
                    >
                        <RatingBreakdown
                            :distribution="tour.rating_distribution"
                            :average="tour.rating_average"
                            :count="tour.rating_count"
                        />
                    </div>
                </div>

                <div class="space-y-4 lg:col-span-2">
                    <div v-if="reviewsLoading" class="space-y-4">
                        <div
                            v-for="n in 3"
                            :key="`review-skel-${n}`"
                            class="h-28 animate-pulse rounded-lg border bg-muted"
                        />
                    </div>

                    <div
                        v-else-if="reviewsLoadError"
                        class="rounded-lg border border-dashed p-6 text-center"
                    >
                        <p class="text-sm text-muted-foreground">
                            {{ $t('No se pudieron cargar las reseñas.') }}
                        </p>
                        <Button
                            variant="outline"
                            size="sm"
                            class="mt-3"
                            @click="loadReviews(1)"
                        >
                            {{ $t('Reintentar') }}
                        </Button>
                    </div>

                    <template v-else>
                        <ReviewCard
                            v-for="review in reviews"
                            :key="review.id"
                            :review="review"
                        />
                        <div v-if="hasMoreReviews" class="pt-2 text-center">
                            <Button
                                variant="outline"
                                :disabled="loadingMoreReviews"
                                @click="loadReviews(reviewsPage + 1)"
                            >
                                {{
                                    loadingMoreReviews
                                        ? 'Cargando...'
                                        : 'Ver más reseñas'
                                }}
                            </Button>
                        </div>
                    </template>
                </div>
            </div>

            <div v-else class="rounded-xl border border-dashed p-8 text-center">
                <Star class="mx-auto size-10 text-muted-foreground/30" />
                <p class="mt-3 font-medium">{{ $t('Aún no hay reseñas') }}</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{
                        $t(
                            'Sé el primero en compartir tu experiencia después de completar el tour.',
                        )
                    }}
                </p>
            </div>
        </div>
    </section>

    <!-- Related tours -->
    <Deferred data="relatedTours">
        <template #fallback>
            <section
                class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8"
            >
                <h2 class="mb-6 text-2xl font-bold">
                    {{ $t('Otras actividades que te podrían gustar') }}
                </h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="n in 4" :key="n" class="space-y-3">
                        <div
                            class="aspect-[4/3] animate-pulse rounded-xl bg-muted"
                        />
                        <div class="h-4 w-3/4 animate-pulse rounded bg-muted" />
                        <div class="h-3 w-1/2 animate-pulse rounded bg-muted" />
                    </div>
                </div>
            </section>
        </template>

        <section
            v-if="relatedTours && relatedTours.length > 0"
            class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8"
        >
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-2xl font-bold">
                    {{ $t('Otras actividades que te podrían gustar') }}
                </h2>
                <Link
                    :href="catalogIndex().url"
                    class="text-sm font-medium text-primary transition hover:underline"
                >
                    {{ $t('Ver todos →') }}
                </Link>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Link
                    v-for="related in relatedTours"
                    :key="related.id"
                    :href="tourShow(related.slug).url"
                    class="group overflow-hidden rounded-xl border bg-background transition hover:shadow-md"
                >
                    <div class="aspect-[4/3] overflow-hidden bg-muted">
                        <img
                            v-if="related.cover_image_url"
                            :src="related.cover_image_url"
                            :alt="related.name"
                            class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                        />
                    </div>
                    <div class="space-y-1.5 p-4">
                        <Badge
                            v-if="related.category"
                            variant="secondary"
                            class="text-[10px]"
                        >
                            {{ related.category.name }}
                        </Badge>
                        <h3 class="leading-tight font-semibold">
                            {{ related.name }}
                        </h3>
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-bold text-primary">
                                {{
                                    formatTourPrice(
                                        related.base_price,
                                        related.currency,
                                    )
                                }}
                            </span>
                            <span
                                v-if="related.rating_count > 0"
                                class="flex items-center gap-1 text-muted-foreground"
                            >
                                <Star
                                    class="size-3 fill-amber-400 text-amber-400"
                                />
                                {{ related.rating_average }}
                            </span>
                        </div>
                    </div>
                </Link>
            </div>
        </section>
    </Deferred>

    <!-- Lightbox -->
    <Teleport to="body">
        <div
            v-if="lightboxOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm"
            @click.self="closeLightbox"
            @keydown.escape="closeLightbox"
        >
            <button
                type="button"
                class="absolute top-4 right-4 rounded-full bg-white/10 p-2 text-white transition hover:bg-white/20"
                @click="closeLightbox"
            >
                <X class="size-6" />
            </button>

            <button
                v-if="tour.images.length > 1"
                type="button"
                class="absolute left-4 rounded-full bg-white/10 p-3 text-white transition hover:bg-white/20"
                @click="prevImage"
            >
                <ChevronLeft class="size-6" />
            </button>

            <div class="max-h-[85vh] max-w-[90vw]">
                <img
                    v-if="activeImage?.url"
                    :src="activeImage.url"
                    :alt="activeImage.alt_text ?? tour.name"
                    class="max-h-[85vh] max-w-[90vw] rounded-lg object-contain"
                />
            </div>

            <button
                v-if="tour.images.length > 1"
                type="button"
                class="absolute right-4 rounded-full bg-white/10 p-3 text-white transition hover:bg-white/20"
                @click="nextImage"
            >
                <ChevronLeft class="size-6 rotate-180" />
            </button>

            <!-- Lightbox thumbnails -->
            <div
                v-if="tour.images.length > 1"
                class="absolute bottom-4 left-1/2 flex -translate-x-1/2 gap-2"
            >
                <button
                    v-for="(img, i) in tour.images"
                    :key="img.id"
                    type="button"
                    class="size-2.5 rounded-full transition"
                    :class="
                        i === activeImageIndex
                            ? 'bg-white'
                            : 'bg-white/40 hover:bg-white/70'
                    "
                    @click="activeImageIndex = i"
                />
            </div>
        </div>
    </Teleport>
</template>
