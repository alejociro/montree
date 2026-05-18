<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FavoriteButton from '@/components/molecules/FavoriteButton.vue';
import RatingBreakdown from '@/components/molecules/RatingBreakdown.vue';
import DateCard from '@/components/molecules/DateCard.vue';
import ImageGallery from '@/components/organisms/ImageGallery.vue';
import { Badge } from '@/components/ui/badge';
import type { TourDetail } from '@/types/tour-detail';

const props = defineProps<{ tour: TourDetail }>();
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
</script>

<template>
    <Head :title="tour.name" />
    <div class="container mx-auto max-w-5xl space-y-8 px-4 py-8">
        <header class="space-y-3">
            <div class="flex items-start justify-between gap-3">
                <div class="space-y-2">
                    <Badge v-if="tour.category" variant="secondary">
                        {{ tour.category.name }}
                    </Badge>
                    <h1 class="text-3xl font-bold">{{ tour.name }}</h1>
                    <p v-if="tour.short_description" class="text-muted-foreground">
                        {{ tour.short_description }}
                    </p>
                    <div class="flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                        <span>{{ tour.duration_hours }} hs</span>
                        <span>·</span>
                        <span class="capitalize">{{ tour.difficulty }}</span>
                        <span>·</span>
                        <span>{{ tour.rating_average }} ★ ({{ tour.rating_count }})</span>
                    </div>
                </div>
                <FavoriteButton
                    v-if="isAuthenticated"
                    :tour-id="tour.id"
                    :initial-favorite="tour.is_favorite"
                />
            </div>
        </header>

        <ImageGallery :images="tour.images" :tour-name="tour.name" />

        <section class="grid gap-8 lg:grid-cols-3">
            <div class="space-y-8 lg:col-span-2">
                <div class="space-y-3">
                    <h2 class="text-xl font-semibold">Descripción</h2>
                    <p class="whitespace-pre-line text-muted-foreground">{{ tour.description }}</p>
                </div>

                <div v-if="tour.includes.length > 0" class="space-y-3">
                    <h2 class="text-xl font-semibold">Incluye</h2>
                    <ul class="list-inside list-disc space-y-1 text-muted-foreground">
                        <li v-for="(item, i) in tour.includes" :key="i">{{ item }}</li>
                    </ul>
                </div>

                <div v-if="tour.requirements.length > 0" class="space-y-3">
                    <h2 class="text-xl font-semibold">Requisitos</h2>
                    <ul class="list-inside list-disc space-y-1 text-muted-foreground">
                        <li v-for="(item, i) in tour.requirements" :key="i">{{ item }}</li>
                    </ul>
                </div>

                <div v-if="tour.itinerary.length > 0" class="space-y-3">
                    <h2 class="text-xl font-semibold">Itinerario</h2>
                    <ol class="space-y-3 border-l-2 border-primary/30 pl-4">
                        <li v-for="step in tour.itinerary" :key="step.step_number" class="space-y-1">
                            <p class="font-medium">
                                {{ step.step_number }}. {{ step.title }}
                                <span v-if="step.duration_label" class="text-sm text-muted-foreground">
                                    · {{ step.duration_label }}
                                </span>
                            </p>
                            <p v-if="step.description" class="text-sm text-muted-foreground">
                                {{ step.description }}
                            </p>
                        </li>
                    </ol>
                </div>

                <div v-if="tour.meeting_point || mapUrl" class="space-y-3">
                    <h2 class="text-xl font-semibold">Punto de encuentro</h2>
                    <p v-if="tour.meeting_point" class="text-muted-foreground">{{ tour.meeting_point }}</p>
                    <a
                        v-if="mapUrl"
                        :href="mapUrl"
                        target="_blank"
                        rel="noopener"
                        class="text-sm text-primary hover:underline"
                    >
                        Ver en Google Maps →
                    </a>
                </div>
            </div>

            <aside class="space-y-4">
                <div class="rounded-xl border p-5">
                    <p class="text-sm text-muted-foreground">Desde</p>
                    <p class="text-3xl font-bold text-primary">{{ formattedPrice }}</p>
                    <p class="text-xs text-muted-foreground">por viajero</p>
                </div>

                <div class="space-y-3">
                    <h2 class="text-xl font-semibold">Fechas disponibles</h2>
                    <div v-if="tour.future_dates.length > 0" class="space-y-2">
                        <DateCard
                            v-for="d in tour.future_dates"
                            :key="d.id"
                            :date="d"
                            :currency="tour.currency"
                        />
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        No hay fechas disponibles por ahora.
                    </p>
                </div>
            </aside>
        </section>

        <section v-if="tour.rating_count > 0" class="space-y-4">
            <h2 class="text-xl font-semibold">Reseñas</h2>
            <RatingBreakdown
                :distribution="tour.rating_distribution"
                :average="tour.rating_average"
                :count="tour.rating_count"
            />
            <p class="text-sm text-muted-foreground">
                Las reseñas se cargan desde
                <code class="rounded bg-muted px-1">/api/v1/tours/{{ tour.slug }}/reviews</code>.
            </p>
        </section>
        <section v-else class="rounded-lg border border-dashed p-6 text-center">
            <p class="text-sm text-muted-foreground">Sé el primero en opinar después de completar el tour.</p>
        </section>
    </div>
</template>
