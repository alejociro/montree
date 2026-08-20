<script setup lang="ts">
import { Deferred, Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { index as tourReviewsIndex } from '@/actions/App/Http/Controllers/Api/V1/PublicReviewController';
import HomeTourCard from '@/components/molecules/HomeTourCard.vue';
import RatingBreakdown from '@/components/molecules/RatingBreakdown.vue';
import ReviewCard from '@/components/molecules/ReviewCard.vue';
import TourFactGrid from '@/components/molecules/TourFactGrid.vue';
import TourInclusionList from '@/components/molecules/TourInclusionList.vue';
import TourItineraryDay from '@/components/molecules/TourItineraryDay.vue';
import TourLogisticsCard from '@/components/molecules/TourLogisticsCard.vue';
import TourBookingCard from '@/components/organisms/TourBookingCard.vue';
import TourGallery from '@/components/organisms/TourGallery.vue';
import TourRouteMapSection from '@/components/organisms/TourRouteMapSection.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { categoryLabel } from '@/lib/categories';
import {
    routeStopsFromTour,
    stopIndexForItineraryStep,
} from '@/lib/tour-route';
import { index as catalogIndex } from '@/routes/catalog';
import type { CatalogTour } from '@/types/catalog';
import type { ReviewSummary, TourDetail, TourFact } from '@/types/tour-detail';

const { t, tChoice } = useTranslations();

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    tour: TourDetail;
    relatedTours?: CatalogTour[];
}>();

const page = usePage();
const isAuthenticated = computed(() => page.props.auth?.user != null);

const routeStops = computed(() => routeStopsFromTour(props.tour));

const mapSection = ref<InstanceType<typeof TourRouteMapSection> | null>(null);

const pickupStopIndex = computed(() => {
    const index = routeStops.value.findIndex((stop) => stop.kind === 'pickup');

    return index === -1 ? null : index;
});

/** Paso del itinerario → índice de su parada, para el botón "Ver en el mapa". */
const stopIndexByStep = computed(() =>
    props.tour.itinerary.reduce<Record<number, number>>((map, step) => {
        const index = stopIndexForItineraryStep(
            routeStops.value,
            step.step_number,
        );

        if (index !== null) {
            map[step.step_number] = index;
        }

        return map;
    }, {}),
);

/** El offset del header sticky obliga a scrollear a mano: `scrollIntoView` lo tapa. */
const MAP_SCROLL_OFFSET = 70;
const MAP_SELECT_DELAY_MS = 350;

function showStopOnMap(index: number | undefined): void {
    const section = document.getElementById('ruta');

    if (index === undefined || section === null) {
        return;
    }

    window.scrollTo({
        top:
            section.getBoundingClientRect().top +
            window.scrollY -
            MAP_SCROLL_OFFSET,
        behavior: 'smooth',
    });
    window.setTimeout(
        () => mapSection.value?.selectStop(index),
        MAP_SELECT_DELAY_MS,
    );
}

const routeNote = computed(() =>
    props.tour.meeting_point === null
        ? null
        : t('Punto de encuentro: :place.', { place: props.tour.meeting_point }),
);

const difficultyLabel = computed(() => {
    const map: Record<string, string> = {
        easy: t('Fácil'),
        moderate: t('Moderado'),
        hard: t('Difícil'),
        extreme: t('Extremo'),
    };

    return map[props.tour.difficulty] ?? props.tour.difficulty;
});

const durationLabel = computed(() =>
    t(':count h', { count: props.tour.duration_hours }),
);

/** Chips del encabezado: solo se pintan los que el tour respalda con datos. */
const highlightChips = computed(() => {
    const chips: string[] = [];

    if (pickupStopIndex.value !== null) {
        chips.push(t('Recogida incluida'));
    }

    chips.push(t('Grupo máx. :count', { count: props.tour.default_capacity }));
    chips.push(t('Dificultad: :level', { level: difficultyLabel.value }));

    return chips;
});

const facts = computed<TourFact[]>(() => {
    const list: TourFact[] = [
        { label: t('Duración'), value: durationLabel.value },
        { label: t('Dificultad'), value: difficultyLabel.value },
        {
            label: t('Grupo máx.'),
            value: tChoice(
                ':count persona|:count personas',
                props.tour.default_capacity,
            ),
        },
    ];

    if (props.tour.category) {
        list.push({
            label: t('Categoría'),
            value: categoryLabel(props.tour.category.name),
        });
    } else if (routeStops.value.length > 0) {
        list.push({
            label: t('Paradas'),
            value: String(routeStops.value.length),
        });
    }

    return list;
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

const selectableDates = computed(() =>
    props.tour.future_dates.filter(
        (date) => !date.is_full && date.status === 'open',
    ),
);

const selectedDateId = ref<number | null>(null);
</script>

<template>
    <Head :title="tour.name" />

    <div class="mx-auto w-full max-w-[1180px] px-4 sm:px-7">
        <!-- Breadcrumb -->
        <nav
            class="flex flex-wrap items-center gap-2 pt-5.5 pb-3.5 text-[11px] tracking-[0.08em] text-muted-foreground uppercase"
        >
            <Link
                :href="catalogIndex().url"
                class="transition hover:text-foreground"
            >
                {{ $t('Tours') }}
            </Link>
            <template v-if="tour.category">
                <span aria-hidden="true">›</span>
                <span>{{ categoryLabel(tour.category.name) }}</span>
            </template>
            <span aria-hidden="true">›</span>
            <span class="truncate text-foreground">{{ tour.name }}</span>
        </nav>

        <!-- Título, meta y chips -->
        <div class="flex flex-wrap items-end justify-between gap-8">
            <div>
                <h1
                    class="max-w-[16ch] text-[34px] leading-[1.02] font-semibold tracking-tight lg:text-[44px]"
                >
                    {{ tour.name }}
                </h1>
                <div
                    class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[13.5px] text-muted-foreground"
                >
                    <span v-if="tour.rating_count > 0">
                        <b class="font-semibold text-foreground">{{
                            tour.rating_average
                        }}</b>
                        <span class="ml-1 text-primary" aria-hidden="true">{{
                            '★'.repeat(Math.round(Number(tour.rating_average)))
                        }}</span>
                        ·
                        {{
                            $tc(
                                ':count reseña|:count reseñas',
                                tour.rating_count,
                            )
                        }}
                    </span>
                    <span v-if="tour.meeting_point">{{
                        tour.meeting_point
                    }}</span>
                    <span>{{ durationLabel }}</span>
                </div>
            </div>

            <ul class="flex flex-wrap gap-2">
                <li
                    v-for="chip in highlightChips"
                    :key="chip"
                    class="rounded-full bg-secondary px-3 py-1.5 text-[12.5px] font-medium text-secondary-foreground"
                >
                    {{ chip }}
                </li>
            </ul>
        </div>

        <!-- Galería -->
        <div class="mt-5">
            <TourGallery
                :images="tour.images"
                :tour-id="tour.id"
                :tour-name="tour.name"
                :is-favorite="tour.is_favorite"
                :is-authenticated="isAuthenticated"
            />
        </div>

        <!-- Contenido + columna de reserva -->
        <div
            class="grid gap-10 pt-8 pb-14 lg:grid-cols-[minmax(0,1fr)_366px] lg:items-start"
        >
            <main class="min-w-0">
                <!-- Descripción y datos duros -->
                <section class="pt-1.5 pb-6">
                    <p
                        class="max-w-[62ch] text-[16.5px] whitespace-pre-line text-foreground/85"
                    >
                        {{ tour.description }}
                    </p>
                    <TourFactGrid :facts="facts" />
                </section>

                <!-- Itinerario -->
                <section
                    v-if="tour.itinerary.length > 0"
                    class="border-t border-border py-6"
                >
                    <h2 class="text-[26px] font-semibold tracking-tight">
                        {{ $t('Itinerario') }}
                    </h2>
                    <p class="mt-1 mb-4.5 text-[13.5px] text-muted-foreground">
                        {{
                            $t(
                                'Cada paso está anclado a un punto del mapa: toca "Ver en el mapa" para ubicarlo.',
                            )
                        }}
                    </p>
                    <TourItineraryDay
                        v-for="step in tour.itinerary"
                        :key="step.step_number"
                        :step="step"
                        :mappable="
                            stopIndexByStep[step.step_number] !== undefined
                        "
                        @show-on-map="
                            showStopOnMap(stopIndexByStep[step.step_number])
                        "
                    />
                </section>

                <!-- Ruta y puntos de encuentro -->
                <TourRouteMapSection
                    v-if="routeStops.length > 0"
                    ref="mapSection"
                    class="border-t border-border py-6"
                    :stops="routeStops"
                    :note="routeNote"
                />

                <!-- Punto de encuentro sin coordenadas: no hay mapa que dibujar -->
                <section
                    v-else-if="tour.meeting_point"
                    class="border-t border-border py-6"
                >
                    <h2 class="text-[26px] font-semibold tracking-tight">
                        {{ $t('Punto de encuentro') }}
                    </h2>
                    <p class="mt-2 text-[13.5px] text-muted-foreground">
                        {{ tour.meeting_point }}
                    </p>
                </section>

                <!-- Qué incluye -->
                <section
                    v-if="tour.includes.length > 0 || tour.excludes.length > 0"
                    class="border-t border-border py-6"
                >
                    <h2 class="text-[26px] font-semibold tracking-tight">
                        {{ $t('Qué incluye') }}
                    </h2>
                    <TourInclusionList
                        :includes="tour.includes"
                        :excludes="tour.excludes"
                    />
                </section>

                <!-- Recomendaciones -->
                <section
                    v-if="tour.requirements.length > 0"
                    class="border-t border-border py-6"
                >
                    <h2 class="text-[26px] font-semibold tracking-tight">
                        {{ $t('Recomendaciones') }}
                    </h2>
                    <ul
                        class="mt-2 max-w-[62ch] space-y-1.5 text-[13.5px] text-muted-foreground"
                    >
                        <li
                            v-for="(item, index) in tour.requirements"
                            :key="`requirement-${index}`"
                            class="flex items-start gap-2"
                        >
                            <span class="mt-0.5 shrink-0" aria-hidden="true"
                                >•</span
                            >
                            {{ item }}
                        </li>
                    </ul>
                </section>

                <!-- Calificaciones y reseñas -->
                <section class="border-t border-border py-6">
                    <h2 class="text-[26px] font-semibold tracking-tight">
                        {{ $t('Calificaciones y reseñas') }}
                    </h2>

                    <div
                        v-if="tour.rating_count > 0"
                        class="mt-4.5 grid gap-[34px] lg:grid-cols-[260px_minmax(0,1fr)] lg:items-start"
                    >
                        <RatingBreakdown
                            :distribution="tour.rating_distribution"
                            :average="tour.rating_average"
                            :count="tour.rating_count"
                        />

                        <div class="space-y-3.5">
                            <div v-if="reviewsLoading" class="space-y-3.5">
                                <div
                                    v-for="n in 2"
                                    :key="`review-skel-${n}`"
                                    class="h-32 animate-pulse rounded-2xl bg-muted"
                                />
                            </div>

                            <div
                                v-else-if="reviewsLoadError"
                                class="rounded-2xl border border-dashed border-border p-6 text-center"
                            >
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        $t('No se pudieron cargar las reseñas.')
                                    }}
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
                                <div
                                    v-if="hasMoreReviews"
                                    class="pt-2 text-center"
                                >
                                    <button
                                        type="button"
                                        class="text-sm font-medium text-primary transition hover:text-brand-ink hover:underline"
                                        :disabled="loadingMoreReviews"
                                        @click="loadReviews(reviewsPage + 1)"
                                    >
                                        {{
                                            loadingMoreReviews
                                                ? $t('Cargando...')
                                                : $t('Ver más reseñas')
                                        }}
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div
                        v-else
                        class="mt-4.5 rounded-2xl border border-dashed border-border p-8 text-center"
                    >
                        <p class="font-medium">
                            {{ $t('Aún no hay reseñas') }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{
                                $t(
                                    'Sé el primero en compartir tu experiencia después de completar el tour.',
                                )
                            }}
                        </p>
                    </div>
                </section>
            </main>

            <TourBookingCard
                :tour="tour"
                :dates="selectableDates"
                :selected-date-id="selectedDateId"
                :pickup-stop-index="pickupStopIndex"
                @update:selected-date-id="selectedDateId = $event"
                @show-pickup="showStopOnMap(pickupStopIndex ?? undefined)"
            >
                <template #logistics>
                    <TourLogisticsCard
                        v-if="routeStops.length > 0"
                        :stops="routeStops"
                        @select="showStopOnMap($event)"
                    />
                </template>
            </TourBookingCard>
        </div>

        <!-- Otras actividades -->
        <Deferred data="relatedTours">
            <template #fallback>
                <section class="border-t border-border pt-8 pb-14">
                    <h2 class="text-[26px] font-semibold tracking-tight">
                        {{ $t('Otras actividades que te podrían gustar') }}
                    </h2>
                    <div
                        class="mt-4.5 grid gap-4.5 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div
                            v-for="n in 4"
                            :key="`related-skel-${n}`"
                            class="h-64 animate-pulse rounded-2xl bg-muted"
                        />
                    </div>
                </section>
            </template>

            <section
                v-if="relatedTours && relatedTours.length > 0"
                class="border-t border-border pt-8 pb-14"
            >
                <div
                    class="flex flex-wrap items-baseline justify-between gap-3"
                >
                    <h2 class="text-[26px] font-semibold tracking-tight">
                        {{ $t('Otras actividades que te podrían gustar') }}
                    </h2>
                    <Link
                        :href="catalogIndex().url"
                        class="text-sm font-medium text-primary transition hover:text-brand-ink hover:underline"
                    >
                        {{ $t('Ver todas ↗') }}
                    </Link>
                </div>

                <div class="mt-4.5 grid gap-4.5 sm:grid-cols-2 lg:grid-cols-4">
                    <HomeTourCard
                        v-for="related in relatedTours"
                        :key="related.id"
                        :tour="related"
                        :is-authenticated="isAuthenticated"
                    />
                </div>
            </section>
        </Deferred>
    </div>
</template>
