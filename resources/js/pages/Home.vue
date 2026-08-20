<script setup lang="ts">
import { Deferred, Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarDays,
    Compass,
    Lock,
    Mountain,
    Palette,
    Quote,
    Search,
    ShieldCheck,
    Star,
    Users,
    Zap,
} from 'lucide-vue-next';
import type { Component } from 'vue';
import { computed, ref } from 'vue';
import { create as bookingCreate } from '@/actions/App/Http/Controllers/BookingPagesController';
import { show as tourShow } from '@/actions/App/Http/Controllers/PublicTourPageController';
import HomeTourCard from '@/components/molecules/HomeTourCard.vue';
import { Button } from '@/components/ui/button';
import { useTenant } from '@/composables/useTenant';
import { useTranslations } from '@/composables/useTranslations';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { formatCurrency, formatTourDate } from '@/lib/format';
import { index as catalogIndex } from '@/routes/catalog';
import type { CatalogCategory, CatalogTour } from '@/types/catalog';

const { t } = useTranslations();
import type {
    HomePromotion,
    HomeTestimonial,
    UpcomingDeparture,
} from '@/types/home';

defineOptions({ layout: PublicLayout });

type Props = {
    featuredTours?: CatalogTour[];
    suggestedTours?: CatalogTour[];
    promotions?: HomePromotion[];
    categories?: CatalogCategory[];
    testimonials?: HomeTestimonial[];
    upcomingDepartures?: UpcomingDeparture[];
};

const LOW_SEATS_THRESHOLD = 3;

defineProps<Props>();

const { displayName, configuration } = useTenant();
const page = usePage();
const isAuthenticated = computed(() => page.props.auth?.user != null);

const heroFallbackImage =
    'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?w=1800&q=80&auto=format&fit=crop';

const searchQuery = ref('');

const CATEGORY_ICONS: Record<string, Component> = {
    mountain: Mountain,
    compass: Compass,
    palette: Palette,
};

function categoryIcon(icon: string | null): Component {
    return (icon !== null ? CATEGORY_ICONS[icon] : undefined) ?? Compass;
}

function categoryHref(slug: string): string {
    return catalogIndex({ query: { category: slug } }).url;
}

function handleSearch(): void {
    const term = searchQuery.value.trim();
    router.get(catalogIndex().url, term ? { search: term } : {});
}

function departureBookingHref(departure: UpcomingDeparture): string {
    return bookingCreate({ query: { tour_date_id: departure.id } }).url;
}

function departureDateLabel(departure: UpcomingDeparture): string {
    const start = formatTourDate(departure.starts_at, { withWeekday: false });

    if (departure.ends_at === null) {
        return start;
    }

    const end = formatTourDate(departure.ends_at, {
        withWeekday: false,
        withTime: true,
    });

    return t(':start hasta :end', { start, end });
}
</script>

<template>
    <div>
        <Head :title="displayName" />

        <!-- Hero -->
        <section class="relative min-h-[70vh] overflow-hidden">
            <img
                :src="configuration?.hero_image_url ?? heroFallbackImage"
                alt=""
                class="absolute inset-0 size-full object-cover"
            />
            <div
                class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/45 to-black/25"
                aria-hidden="true"
            />
            <div
                class="absolute inset-0 bg-gradient-to-br from-primary/35 via-transparent to-transparent mix-blend-multiply"
                aria-hidden="true"
            />

            <div
                class="relative z-10 mx-auto flex min-h-[70vh] w-full max-w-5xl flex-col items-center justify-center px-4 py-24 text-center sm:px-6 lg:px-8"
            >
                <h1
                    class="max-w-3xl text-4xl font-bold tracking-tight text-white drop-shadow-md sm:text-5xl lg:text-6xl"
                >
                    {{
                        configuration?.tagline ||
                        $t('Encuentra tu próxima aventura')
                    }}
                </h1>
                <p
                    class="mt-4 max-w-2xl text-base text-white/90 sm:text-lg lg:text-xl"
                >
                    {{
                        configuration?.description ||
                        $t('Explora experiencias inolvidables con nosotros')
                    }}
                </p>

                <form
                    class="mt-8 flex w-full max-w-2xl items-center gap-2 rounded-full bg-background/95 p-2 shadow-2xl ring-1 ring-black/5 backdrop-blur"
                    role="search"
                    :aria-label="$t('Buscar tours')"
                    @submit.prevent="handleSearch"
                >
                    <div class="relative flex-1">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-muted-foreground"
                        />
                        <label class="sr-only" for="hero-search">
                            {{ $t('Buscar tours') }}
                        </label>
                        <input
                            id="hero-search"
                            v-model="searchQuery"
                            type="text"
                            :placeholder="
                                $t('Buscar tours, experiencias o destinos...')
                            "
                            class="w-full rounded-full border-0 bg-transparent py-2.5 pr-4 pl-11 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none"
                        />
                    </div>
                    <Button type="submit" class="rounded-full px-6">
                        {{ $t('Buscar') }}
                        <ArrowRight class="ml-1 size-4" />
                    </Button>
                </form>

                <!-- Category chips -->
                <Deferred data="categories">
                    <template #fallback>
                        <div class="mt-6 flex flex-wrap justify-center gap-2">
                            <div
                                v-for="n in 5"
                                :key="`cat-chip-skel-${n}`"
                                class="h-8 w-24 animate-pulse rounded-full bg-white/20"
                            />
                        </div>
                    </template>

                    <div
                        v-if="(categories?.length ?? 0) > 0"
                        class="mt-6 flex flex-wrap justify-center gap-2"
                    >
                        <Link
                            v-for="category in categories"
                            :key="category.id"
                            :href="categoryHref(category.slug)"
                            class="inline-flex items-center gap-1.5 rounded-full border border-white/25 bg-white/10 px-3.5 py-1.5 text-xs font-medium text-white backdrop-blur transition hover:bg-white/20 focus-visible:ring-2 focus-visible:ring-white/70 focus-visible:outline-none"
                        >
                            <component
                                :is="categoryIcon(category.icon)"
                                class="size-3.5"
                                aria-hidden="true"
                            />
                            {{ category.name }}
                            <span class="text-white/60"
                                >({{ category.tours_count }})</span
                            >
                        </Link>
                    </div>
                </Deferred>

                <!-- Trust indicators -->
                <div
                    class="mt-10 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-xs font-medium text-white/85 sm:text-sm"
                >
                    <span class="inline-flex items-center gap-1.5">
                        <ShieldCheck class="size-4" aria-hidden="true" />
                        {{ $t('Reserva segura') }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <Zap class="size-4" aria-hidden="true" />
                        {{ $t('Confirmación instantánea') }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <Lock class="size-4" aria-hidden="true" />
                        {{ $t('Pago protegido') }}
                    </span>
                </div>
            </div>
        </section>

        <!-- Featured categories -->
        <Deferred data="categories">
            <template #fallback>
                <section
                    class="mx-auto w-full max-w-7xl px-4 pt-14 sm:px-6 lg:px-8"
                >
                    <div class="h-7 w-56 animate-pulse rounded bg-muted" />
                    <div
                        class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4"
                    >
                        <div
                            v-for="n in 4"
                            :key="`cat-card-skel-${n}`"
                            class="h-28 animate-pulse rounded-2xl bg-muted"
                        />
                    </div>
                </section>
            </template>

            <section
                v-if="(categories?.length ?? 0) >= 3"
                class="mx-auto w-full max-w-7xl px-4 pt-14 sm:px-6 lg:px-8"
            >
                <h2
                    class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl"
                >
                    {{ $t('Explora por categoría') }}
                </h2>
                <div
                    class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4"
                >
                    <Link
                        v-for="category in categories"
                        :key="category.id"
                        :href="categoryHref(category.slug)"
                        class="group flex flex-col items-start gap-3 rounded-2xl border border-border/60 bg-card p-5 shadow-sm transition duration-300 hover:-translate-y-1 hover:border-primary/40 hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
                    >
                        <span
                            class="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary transition group-hover:bg-primary group-hover:text-primary-foreground"
                        >
                            <component
                                :is="categoryIcon(category.icon)"
                                class="size-5"
                                aria-hidden="true"
                            />
                        </span>
                        <div>
                            <p class="text-sm font-bold text-foreground">
                                {{ category.name }}
                            </p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{
                                    $tc(
                                        ':count tour|:count tours',
                                        category.tours_count,
                                    )
                                }}
                            </p>
                        </div>
                    </Link>
                </div>
            </section>
        </Deferred>

        <!-- Featured tours -->
        <section class="mx-auto w-full max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2
                        class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl"
                    >
                        {{ $t('Tours destacados') }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            $t('Nuestras experiencias más reservadas este mes')
                        }}
                    </p>
                </div>
                <Link
                    :href="catalogIndex().url"
                    class="flex shrink-0 items-center gap-1 text-sm font-medium text-primary transition hover:underline"
                >
                    {{ $t('Ver todos') }}
                    <ArrowRight class="size-4" />
                </Link>
            </div>

            <Deferred data="featuredTours">
                <template #fallback>
                    <div
                        class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div
                            v-for="n in 4"
                            :key="`ft-skel-${n}`"
                            class="flex flex-col gap-3 rounded-2xl bg-card p-3 shadow-sm ring-1 ring-border/50"
                        >
                            <div
                                class="aspect-[4/3] w-full animate-pulse rounded-xl bg-muted"
                            />
                            <div
                                class="mt-1 h-4 w-2/3 animate-pulse rounded bg-muted"
                            />
                            <div
                                class="h-3 w-1/2 animate-pulse rounded bg-muted"
                            />
                            <div
                                class="mt-1 h-5 w-24 animate-pulse rounded bg-muted"
                            />
                        </div>
                    </div>
                </template>

                <div
                    v-if="(featuredTours?.length ?? 0) > 0"
                    class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <HomeTourCard
                        v-for="tour in featuredTours"
                        :key="tour.id"
                        :tour="tour"
                        :is-authenticated="isAuthenticated"
                    />
                </div>

                <div
                    v-else
                    class="mt-8 flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-muted/30 px-6 py-16 text-center"
                >
                    <Compass
                        class="size-10 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <h3 class="mt-4 text-lg font-semibold text-foreground">
                        {{ $t('Aún no hay tours publicados') }}
                    </h3>
                    <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                        {{
                            $t(
                                'Muy pronto encontrarás aquí experiencias increíbles para reservar.',
                            )
                        }}
                    </p>
                </div>
            </Deferred>
        </section>

        <!-- Upcoming departures -->
        <Deferred data="upcomingDepartures">
            <template #fallback>
                <section
                    class="mx-auto w-full max-w-7xl px-4 pb-14 sm:px-6 lg:px-8"
                >
                    <div class="h-7 w-56 animate-pulse rounded bg-muted" />
                    <div
                        class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <div
                            v-for="n in 3"
                            :key="`dep-skel-${n}`"
                            class="flex flex-col gap-3 rounded-2xl bg-card p-3 shadow-sm ring-1 ring-border/50"
                        >
                            <div
                                class="aspect-[16/10] w-full animate-pulse rounded-xl bg-muted"
                            />
                            <div
                                class="mt-1 h-4 w-2/3 animate-pulse rounded bg-muted"
                            />
                            <div
                                class="h-3 w-1/2 animate-pulse rounded bg-muted"
                            />
                            <div
                                class="mt-1 h-9 w-full animate-pulse rounded-full bg-muted"
                            />
                        </div>
                    </div>
                </section>
            </template>

            <section
                v-if="(upcomingDepartures?.length ?? 0) > 0"
                class="mx-auto w-full max-w-7xl px-4 pb-14 sm:px-6 lg:px-8"
            >
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <h2
                            class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl"
                        >
                            {{ $t('Próximas salidas') }}
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{
                                $t(
                                    'Reserva tu cupo en las próximas fechas confirmadas',
                                )
                            }}
                        </p>
                    </div>
                </div>

                <div
                    class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <article
                        v-for="departure in upcomingDepartures"
                        :key="departure.id"
                        class="group relative flex flex-col overflow-hidden rounded-2xl bg-card shadow-sm ring-1 ring-border/50 transition duration-300 hover:-translate-y-1 hover:shadow-xl"
                    >
                        <div class="relative aspect-[16/10] overflow-hidden">
                            <img
                                v-if="departure.tour.cover_image_url"
                                :src="departure.tour.cover_image_url"
                                :alt="departure.tour.name"
                                loading="lazy"
                                class="size-full object-cover transition-transform duration-500 group-hover:scale-105"
                            />
                            <div
                                v-else
                                class="flex size-full items-center justify-center bg-muted"
                            >
                                <span class="text-xs text-muted-foreground">{{
                                    $t('Sin imagen')
                                }}</span>
                            </div>
                            <div
                                class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"
                                aria-hidden="true"
                            />
                            <span
                                v-if="
                                    departure.available_seats <=
                                    LOW_SEATS_THRESHOLD
                                "
                                class="absolute top-4 left-4 rounded-full bg-destructive px-3.5 py-1.5 text-xs font-extrabold tracking-wide text-destructive-foreground shadow-lg"
                            >
                                {{
                                    $t('¡Últimos :count cupos!', {
                                        count: departure.available_seats,
                                    })
                                }}
                            </span>
                        </div>
                        <div class="flex flex-1 flex-col gap-3 p-5">
                            <h3 class="text-lg font-bold text-foreground">
                                <Link
                                    :href="tourShow.url(departure.tour.slug)"
                                    class="transition hover:text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
                                >
                                    {{ departure.tour.name }}
                                </Link>
                            </h3>

                            <div
                                class="flex items-start gap-2 text-sm text-muted-foreground"
                            >
                                <CalendarDays
                                    class="mt-0.5 size-4 shrink-0 text-primary"
                                    aria-hidden="true"
                                />
                                <span>{{ departureDateLabel(departure) }}</span>
                            </div>

                            <div
                                class="flex items-center gap-2 text-sm"
                                :class="
                                    departure.available_seats <=
                                    LOW_SEATS_THRESHOLD
                                        ? 'font-semibold text-destructive'
                                        : 'text-muted-foreground'
                                "
                            >
                                <Users
                                    class="size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                <span>
                                    {{
                                        $tc(
                                            ':count cupo disponible|:count cupos disponibles',
                                            departure.available_seats,
                                        )
                                    }}
                                </span>
                            </div>

                            <p
                                class="text-xl font-bold text-foreground"
                                :aria-label="$t('Precio por persona')"
                            >
                                {{
                                    formatCurrency(
                                        departure.effective_price,
                                        departure.tour.currency,
                                    )
                                }}
                                <span
                                    class="text-sm font-normal text-muted-foreground"
                                    >{{ $t('/ persona') }}</span
                                >
                            </p>

                            <div class="mt-auto pt-2">
                                <Button as-child class="w-full rounded-full">
                                    <Link
                                        :href="departureBookingHref(departure)"
                                    >
                                        {{ $t('Reservar') }}
                                        <ArrowRight class="ml-1 size-4" />
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </Deferred>

        <!-- Promotions -->
        <Deferred data="promotions">
            <template #fallback>
                <section
                    class="mx-auto w-full max-w-7xl px-4 pb-14 sm:px-6 lg:px-8"
                >
                    <div class="h-7 w-64 animate-pulse rounded bg-muted" />
                    <div
                        class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <div
                            v-for="n in 3"
                            :key="`promo-skel-${n}`"
                            class="h-64 animate-pulse rounded-2xl bg-muted"
                        />
                    </div>
                </section>
            </template>

            <section
                v-if="(promotions?.length ?? 0) > 0"
                class="mx-auto w-full max-w-7xl px-4 pb-14 sm:px-6 lg:px-8"
            >
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <h2
                            class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl"
                        >
                            {{ $t('Promociones especiales') }}
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ $t('Aprovecha nuestras ofertas de temporada') }}
                        </p>
                    </div>
                </div>

                <div
                    class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <article
                        v-for="promo in promotions"
                        :key="promo.id"
                        class="group relative flex flex-col overflow-hidden rounded-2xl bg-card shadow-sm ring-1 ring-border/50 transition duration-300 hover:-translate-y-1 hover:shadow-xl"
                    >
                        <div class="relative aspect-[16/10] overflow-hidden">
                            <img
                                v-if="promo.cover_image_url"
                                :src="promo.cover_image_url"
                                :alt="promo.name"
                                loading="lazy"
                                class="size-full object-cover transition-transform duration-500 group-hover:scale-105"
                            />
                            <div
                                v-else
                                class="flex size-full items-center justify-center bg-muted"
                            >
                                <span class="text-xs text-muted-foreground">{{
                                    $t('Sin imagen')
                                }}</span>
                            </div>
                            <div
                                class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"
                                aria-hidden="true"
                            />
                            <span
                                class="absolute top-4 left-4 rounded-full bg-destructive px-3.5 py-1.5 text-sm font-extrabold tracking-wide text-destructive-foreground shadow-lg"
                            >
                                {{
                                    $t(':discount OFF', {
                                        discount: promo.discount_label,
                                    })
                                }}
                            </span>
                        </div>
                        <div class="flex flex-1 flex-col gap-2 p-5">
                            <h3 class="text-lg font-bold text-foreground">
                                {{ promo.name }}
                            </h3>
                            <p
                                v-if="promo.description"
                                class="line-clamp-2 text-sm text-muted-foreground"
                            >
                                {{ promo.description }}
                            </p>
                            <div class="mt-auto pt-3">
                                <Button
                                    v-if="promo.tour"
                                    as-child
                                    class="rounded-full px-6"
                                >
                                    <Link :href="tourShow.url(promo.tour.slug)">
                                        {{ $t('Reservar ahora') }}
                                        <ArrowRight class="ml-1 size-4" />
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </Deferred>

        <!-- Suggestions -->
        <section class="mx-auto w-full max-w-7xl px-4 pb-14 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between gap-4">
                <h2
                    class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl"
                >
                    {{ $t('Sugerencias para ti') }}
                </h2>
                <Link
                    :href="catalogIndex().url"
                    class="flex shrink-0 items-center gap-1 text-sm font-medium text-primary transition hover:underline"
                >
                    {{ $t('Ver todos') }}
                    <ArrowRight class="size-4" />
                </Link>
            </div>

            <Deferred data="suggestedTours">
                <template #fallback>
                    <div
                        class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div
                            v-for="n in 4"
                            :key="`sg-skel-${n}`"
                            class="flex flex-col gap-3 rounded-2xl bg-card p-3 shadow-sm ring-1 ring-border/50"
                        >
                            <div
                                class="aspect-[4/3] w-full animate-pulse rounded-xl bg-muted"
                            />
                            <div
                                class="mt-1 h-4 w-2/3 animate-pulse rounded bg-muted"
                            />
                            <div
                                class="h-5 w-24 animate-pulse rounded bg-muted"
                            />
                        </div>
                    </div>
                </template>

                <div
                    v-if="(suggestedTours?.length ?? 0) > 0"
                    class="-mx-4 mt-8 flex snap-x snap-mandatory gap-6 overflow-x-auto px-4 pb-4 sm:mx-0 sm:grid sm:grid-cols-2 sm:overflow-visible sm:px-0 sm:pb-0 lg:grid-cols-4"
                    role="list"
                    :aria-label="$t('Tours sugeridos')"
                >
                    <HomeTourCard
                        v-for="tour in suggestedTours"
                        :key="tour.id"
                        :tour="tour"
                        :is-authenticated="isAuthenticated"
                        role="listitem"
                        class="w-[80vw] shrink-0 snap-start sm:w-auto"
                    />
                </div>
            </Deferred>
        </section>

        <!-- Testimonials -->
        <Deferred data="testimonials">
            <template #fallback>
                <section class="bg-muted/40 py-16">
                    <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div
                            class="mx-auto h-7 w-72 animate-pulse rounded bg-muted"
                        />
                        <div class="mt-8 grid grid-cols-1 gap-6 md:grid-cols-3">
                            <div
                                v-for="n in 3"
                                :key="`test-skel-${n}`"
                                class="h-44 animate-pulse rounded-2xl bg-muted"
                            />
                        </div>
                    </div>
                </section>
            </template>

            <section
                v-if="(testimonials?.length ?? 0) > 0"
                class="bg-muted/40 py-16"
            >
                <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="text-center">
                        <h2
                            class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl"
                        >
                            {{ $t('Lo que dicen nuestros viajeros') }}
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{
                                $t(
                                    'Experiencias reales de quienes viajaron con nosotros',
                                )
                            }}
                        </p>
                    </div>

                    <div class="mt-10 grid grid-cols-1 gap-6 md:grid-cols-3">
                        <figure
                            v-for="testimonial in testimonials"
                            :key="testimonial.id"
                            class="relative flex flex-col rounded-2xl bg-card p-6 shadow-sm ring-1 ring-border/50"
                        >
                            <Quote
                                class="absolute top-5 right-5 size-8 text-primary/15"
                                aria-hidden="true"
                            />
                            <div
                                class="flex items-center gap-0.5"
                                :aria-label="`${testimonial.rating} de 5 estrellas`"
                            >
                                <Star
                                    v-for="star in 5"
                                    :key="star"
                                    class="size-4"
                                    :class="
                                        star <= testimonial.rating
                                            ? 'fill-amber-400 text-amber-400'
                                            : 'text-muted-foreground/30'
                                    "
                                    aria-hidden="true"
                                />
                            </div>
                            <figcaption class="contents">
                                <h3
                                    v-if="testimonial.title"
                                    class="mt-4 font-semibold text-foreground"
                                >
                                    {{ testimonial.title }}
                                </h3>
                                <blockquote
                                    v-if="testimonial.body"
                                    class="mt-2 line-clamp-4 text-sm text-muted-foreground"
                                >
                                    {{ testimonial.body }}
                                </blockquote>
                                <div
                                    class="mt-auto flex flex-wrap items-center gap-x-1.5 pt-4 text-sm"
                                >
                                    <span class="font-medium text-foreground">
                                        {{
                                            testimonial.author_name ??
                                            $t('Viajero')
                                        }}
                                    </span>
                                    <template v-if="testimonial.tour">
                                        <span class="text-muted-foreground"
                                            >·</span
                                        >
                                        <Link
                                            :href="
                                                tourShow.url(
                                                    testimonial.tour.slug,
                                                )
                                            "
                                            class="text-primary transition hover:underline"
                                        >
                                            {{ testimonial.tour.name }}
                                        </Link>
                                    </template>
                                </div>
                            </figcaption>
                        </figure>
                    </div>
                </div>
            </section>
        </Deferred>
    </div>
</template>
