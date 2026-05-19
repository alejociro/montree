<script setup lang="ts">
import { Deferred, Head, Link, usePage } from '@inertiajs/vue3';
import {
    ChevronLeft,
    Clock,
    MapPin,
    Mountain,
    Star,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FavoriteButton from '@/components/molecules/FavoriteButton.vue';
import RatingBreakdown from '@/components/molecules/RatingBreakdown.vue';
import DateCard from '@/components/molecules/DateCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { index as catalogIndex } from '@/routes/catalog';
import type { TourDetail, TourDetailImage } from '@/types/tour-detail';

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

const formattedPrice = computed(() =>
    new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: props.tour.currency,
        maximumFractionDigits: 0,
    }).format(Number(props.tour.base_price)),
);

const mapUrl = computed(() => {
    if (!props.tour.meeting_latitude || !props.tour.meeting_longitude) {
        return null;
    }
    return `https://maps.google.com/?q=${props.tour.meeting_latitude},${props.tour.meeting_longitude}`;
});

const difficultyLabel = computed(() => {
    const map: Record<string, string> = {
        easy: 'Fácil',
        moderate: 'Moderado',
        hard: 'Difícil',
        expert: 'Experto',
    };
    return map[props.tour.difficulty] ?? props.tour.difficulty;
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
</script>

<template>
    <Head :title="tour.name" />

    <!-- Breadcrumb -->
    <div class="mx-auto w-full max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
        <nav class="flex items-center gap-2 text-sm text-muted-foreground">
            <Link :href="catalogIndex().url" class="transition hover:text-foreground">
                Tours
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
                        class="absolute right-3 top-3"
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
            </div>

            <!-- RIGHT: Tour info -->
            <div class="space-y-6">
                <!-- Category badge -->
                <Badge v-if="tour.category" variant="secondary" class="text-xs">
                    {{ tour.category.name }}
                </Badge>

                <!-- Tour name -->
                <h1 class="text-2xl font-bold leading-tight sm:text-3xl">
                    {{ tour.name }}
                </h1>

                <!-- Quick stats -->
                <div class="flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
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
                        Hasta {{ tour.default_capacity }} personas
                    </span>
                    <span
                        v-if="tour.rating_count > 0"
                        class="flex items-center gap-1"
                    >
                        <Star class="size-4 fill-amber-400 text-amber-400" />
                        {{ tour.rating_average }}
                        <span class="text-muted-foreground">({{ tour.rating_count }})</span>
                    </span>
                </div>

                <!-- Price -->
                <div>
                    <span class="text-sm text-muted-foreground">Desde</span>
                    <p class="text-3xl font-bold text-primary">{{ formattedPrice }}</p>
                    <span class="text-xs text-muted-foreground">por persona</span>
                </div>

                <!-- Description -->
                <div class="space-y-2">
                    <h2 class="text-lg font-semibold">Descripción</h2>
                    <p class="whitespace-pre-line text-sm leading-relaxed text-muted-foreground">
                        {{ tour.description }}
                    </p>
                </div>

                <!-- Includes -->
                <div v-if="tour.includes.length > 0" class="space-y-2">
                    <h2 class="text-lg font-semibold">¿Qué incluye?</h2>
                    <ul class="grid grid-cols-2 gap-1.5 text-sm text-muted-foreground">
                        <li
                            v-for="(item, i) in tour.includes"
                            :key="i"
                            class="flex items-start gap-2"
                        >
                            <span class="mt-0.5 text-primary">✓</span>
                            {{ item }}
                        </li>
                    </ul>
                </div>

                <!-- Requirements -->
                <div v-if="tour.requirements.length > 0" class="space-y-2">
                    <h2 class="text-lg font-semibold">Requisitos</h2>
                    <ul class="space-y-1.5 text-sm text-muted-foreground">
                        <li
                            v-for="(item, i) in tour.requirements"
                            :key="i"
                            class="flex items-start gap-2"
                        >
                            <span class="mt-0.5">•</span>
                            {{ item }}
                        </li>
                    </ul>
                </div>

                <!-- Itinerary timeline -->
                <div v-if="tour.itinerary.length > 0" class="space-y-3">
                    <h2 class="text-lg font-semibold">Itinerario</h2>
                    <div class="relative space-y-0 pl-6">
                        <div
                            class="absolute bottom-2 left-[9px] top-2 w-0.5 bg-primary/20"
                        />
                        <div
                            v-for="step in tour.itinerary"
                            :key="step.step_number"
                            class="relative pb-5 last:pb-0"
                        >
                            <div
                                class="absolute -left-6 top-1 flex size-5 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-primary-foreground"
                            >
                                {{ step.step_number }}
                            </div>
                            <div>
                                <p class="font-medium leading-tight">{{ step.title }}</p>
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

                <!-- Meeting point -->
                <div v-if="tour.meeting_point || mapUrl" class="space-y-2">
                    <h2 class="text-lg font-semibold">Punto de encuentro</h2>
                    <div class="flex items-start gap-2 text-sm text-muted-foreground">
                        <MapPin class="mt-0.5 size-4 shrink-0 text-primary" />
                        <div>
                            <p v-if="tour.meeting_point">{{ tour.meeting_point }}</p>
                            <a
                                v-if="mapUrl"
                                :href="mapUrl"
                                target="_blank"
                                rel="noopener"
                                class="text-primary hover:underline"
                            >
                                Ver en Google Maps →
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Available dates -->
                <div class="space-y-3">
                    <h2 class="text-lg font-semibold">Fechas disponibles</h2>
                    <div v-if="tour.future_dates.length > 0" class="space-y-2">
                        <DateCard
                            v-for="d in tour.future_dates.slice(0, 3)"
                            :key="d.id"
                            :date="d"
                            :currency="tour.currency"
                        />
                        <p
                            v-if="tour.future_dates.length > 3"
                            class="text-center text-xs text-muted-foreground"
                        >
                            +{{ tour.future_dates.length - 3 }} fechas más disponibles
                        </p>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        No hay fechas disponibles por ahora.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Reviews section -->
    <section class="border-t bg-muted/30">
        <div class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <h2 class="mb-8 text-2xl font-bold">Calificaciones y reseñas</h2>

            <div v-if="tour.rating_count > 0" class="grid gap-8 lg:grid-cols-3">
                <div class="lg:col-span-1">
                    <div class="sticky top-24 rounded-xl border bg-background p-6">
                        <RatingBreakdown
                            :distribution="tour.rating_distribution"
                            :average="tour.rating_average"
                            :count="tour.rating_count"
                        />
                    </div>
                </div>

                <div class="space-y-4 lg:col-span-2">
                    <p class="text-sm text-muted-foreground">
                        Las reseñas de los viajeros aparecen aquí después de completar el tour.
                    </p>
                </div>
            </div>

            <div v-else class="rounded-xl border border-dashed p-8 text-center">
                <Star class="mx-auto size-10 text-muted-foreground/30" />
                <p class="mt-3 font-medium">Aún no hay reseñas</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Sé el primero en compartir tu experiencia después de completar el tour.
                </p>
            </div>
        </div>
    </section>

    <!-- Related tours -->
    <Deferred data="relatedTours">
        <template #fallback>
            <section class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <h2 class="mb-6 text-2xl font-bold">Otras actividades que te podrían gustar</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-for="n in 4"
                        :key="n"
                        class="space-y-3"
                    >
                        <div class="aspect-[4/3] animate-pulse rounded-xl bg-muted" />
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
                <h2 class="text-2xl font-bold">Otras actividades que te podrían gustar</h2>
                <Link
                    :href="catalogIndex().url"
                    class="text-sm font-medium text-primary transition hover:underline"
                >
                    Ver todos →
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
                        <h3 class="font-semibold leading-tight">{{ related.name }}</h3>
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-bold text-primary">
                                {{ formatTourPrice(related.base_price, related.currency) }}
                            </span>
                            <span
                                v-if="related.rating_count > 0"
                                class="flex items-center gap-1 text-muted-foreground"
                            >
                                <Star class="size-3 fill-amber-400 text-amber-400" />
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
                class="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white transition hover:bg-white/20"
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
