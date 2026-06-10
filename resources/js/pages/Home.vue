<script setup lang="ts">
import { Deferred, Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowRight, Search, Star } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FavoriteButton from '@/components/molecules/FavoriteButton.vue';
import { Button } from '@/components/ui/button';
import { useTenant } from '@/composables/useTenant';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { index as catalogIndex } from '@/routes/catalog';

defineOptions({ layout: PublicLayout });

type TourCard = {
    id: number;
    slug: string;
    name: string;
    short_description: string | null;
    base_price: string;
    currency: string;
    cover_image_url: string | null;
    rating_average: string;
    rating_count: number;
    category: { name: string } | null;
    is_favorite: boolean;
};

type PromotionCard = {
    id: number;
    name: string;
    description: string | null;
    discount_label: string;
    cover_image_url: string | null;
    tour: { slug: string; name: string } | null;
};

type Props = {
    featuredTours?: TourCard[];
    suggestedTours?: TourCard[];
    promotions?: PromotionCard[];
};

defineProps<Props>();

const { displayName, configuration } = useTenant();
const page = usePage();
const isAuthenticated = computed(() => page.props.auth?.user != null);

const searchQuery = ref('');
const newsletterEmail = ref('');
const newsletterSubmitting = ref(false);
const newsletterSuccess = ref(false);

function handleSearch(): void {
    if (searchQuery.value.trim()) {
        router.get(catalogIndex().url, { search: searchQuery.value.trim() });
    } else {
        router.get(catalogIndex().url);
    }
}

function formatPrice(amount: string, code: string): string {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: code,
        maximumFractionDigits: 0,
    }).format(Number(amount));
}

async function handleNewsletterSubscribe(): Promise<void> {
    if (!newsletterEmail.value.trim() || newsletterSubmitting.value) {
        return;
    }

    newsletterSubmitting.value = true;

    try {
        const response = await fetch('/api/v1/newsletter/subscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie
                        .split('; ')
                        .find((row) => row.startsWith('XSRF-TOKEN='))
                        ?.split('=')[1] ?? '',
                ),
            },
            body: JSON.stringify({ email: newsletterEmail.value.trim() }),
        });

        if (response.ok) {
            newsletterSuccess.value = true;
            newsletterEmail.value = '';
        }
    } finally {
        newsletterSubmitting.value = false;
    }
}
</script>

<template>
    <div>
        <Head :title="displayName" />

        <!-- Hero Section -->
        <section class="relative min-h-[60vh] overflow-hidden">
            <img
                :src="configuration?.hero_image_url ?? 'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?w=1800&q=80&auto=format&fit=crop'"
                alt=""
                class="absolute inset-0 size-full object-cover"
            />
            <div class="absolute inset-0 bg-[#172e24]/60" />

            <div
                class="relative z-10 mx-auto flex min-h-[60vh] w-full max-w-7xl flex-col items-center justify-center px-4 py-24 text-center sm:px-6 lg:px-8"
            >
                <h1
                    class="max-w-3xl text-4xl font-extrabold tracking-tight text-white drop-shadow-sm sm:text-5xl lg:text-6xl"
                >
                    {{
                        configuration?.tagline ||
                        'Encuentra tu próxima aventura'
                    }}
                </h1>
                <p
                    class="mt-4 max-w-2xl text-base text-white/85 sm:text-lg lg:text-xl"
                >
                    {{
                        configuration?.description ||
                        'Explora el mundo con nosotros'
                    }}
                </p>

                <form
                    class="mt-8 flex w-full max-w-2xl items-center gap-2 rounded-full bg-white/95 p-2 shadow-xl ring-1 ring-black/5 backdrop-blur"
                    @submit.prevent="handleSearch"
                >
                    <div class="relative flex-1">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-muted-foreground"
                        />
                        <label class="sr-only" for="hero-search"
                            >Buscar tours</label
                        >
                        <input
                            id="hero-search"
                            v-model="searchQuery"
                            type="text"
                            placeholder="Buscar tours, experiencias o destinos..."
                            class="w-full rounded-full border-0 bg-transparent py-2.5 pr-4 pl-11 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none"
                        />
                    </div>
                    <Button type="submit" class="rounded-full px-6">
                        Buscar
                        <ArrowRight class="ml-1 size-4" />
                    </Button>
                </form>
            </div>
        </section>

        <!-- Featured Tours Section -->
        <section class="mx-auto w-full max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2
                        class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl"
                    >
                        Tours
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Nuestras experiencias más reservadas este mes
                    </p>
                </div>
                <Link
                    :href="catalogIndex().url"
                    class="flex shrink-0 items-center gap-1 text-sm font-medium text-primary transition hover:underline"
                >
                    Ver todos
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
                            class="flex flex-col gap-3 rounded-2xl bg-card p-3 shadow-sm"
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
                            <div class="mt-1 flex items-center justify-between">
                                <div
                                    class="h-5 w-24 animate-pulse rounded bg-muted"
                                />
                                <div
                                    class="h-3 w-14 animate-pulse rounded bg-muted"
                                />
                            </div>
                        </div>
                    </div>
                </template>

                <div
                    class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <article
                        v-for="tour in featuredTours"
                        :key="tour.id"
                        class="group relative flex flex-col rounded-2xl bg-card p-3 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg"
                    >
                        <Link
                            :href="`/tours/${tour.slug}`"
                            class="relative block aspect-[4/3] overflow-hidden rounded-xl"
                        >
                            <img
                                v-if="tour.cover_image_url"
                                :src="tour.cover_image_url"
                                :alt="tour.name"
                                class="size-full object-cover transition-transform duration-500 group-hover:scale-105"
                            />
                            <div
                                v-else
                                class="flex size-full items-center justify-center bg-muted"
                            >
                                <span class="text-xs text-muted-foreground"
                                    >Sin imagen</span
                                >
                            </div>
                        </Link>
                        <div
                            v-if="isAuthenticated"
                            class="absolute top-5 right-5 z-10"
                        >
                            <FavoriteButton
                                :tour-id="tour.id"
                                :initial-favorite="tour.is_favorite"
                                class="size-9 rounded-full border-0 bg-white/90 text-foreground shadow-sm backdrop-blur hover:bg-white"
                            />
                        </div>
                        <div class="flex flex-1 flex-col gap-1 px-1 pt-4 pb-2">
                            <Link
                                :href="`/tours/${tour.slug}`"
                                class="text-base leading-tight font-bold text-foreground transition group-hover:text-primary"
                            >
                                {{ tour.name }}
                            </Link>
                            <p
                                v-if="tour.category"
                                class="text-xs text-muted-foreground"
                            >
                                {{ tour.category.name }}
                            </p>
                            <div
                                class="mt-3 flex items-center justify-between gap-2"
                            >
                                <span
                                    class="text-base font-bold text-foreground"
                                >
                                    {{
                                        formatPrice(
                                            tour.base_price,
                                            tour.currency,
                                        )
                                    }}
                                </span>
                                <span
                                    v-if="tour.rating_count > 0"
                                    class="flex items-center gap-1 text-xs font-medium text-muted-foreground"
                                    :title="`${tour.rating_average} de 5`"
                                >
                                    <Star
                                        class="size-3.5 fill-amber-400 text-amber-400"
                                    />
                                    {{ Number(tour.rating_average).toFixed(1) }}
                                    <span class="text-muted-foreground/70"
                                        >({{ tour.rating_count }})</span
                                    >
                                </span>
                            </div>
                        </div>
                    </article>
                </div>
            </Deferred>
        </section>

        <!-- Suggested Tours Section -->
        <section class="mx-auto w-full max-w-7xl px-4 pb-14 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between gap-4">
                <h2
                    class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl"
                >
                    Sugerencias para ti
                </h2>
                <Link
                    :href="catalogIndex().url"
                    class="flex shrink-0 items-center gap-1 text-sm font-medium text-primary transition hover:underline"
                >
                    Ver todos
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
                            class="flex flex-col gap-3 rounded-2xl bg-card p-3 shadow-sm"
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
                            <div class="mt-1 flex items-center justify-between">
                                <div
                                    class="h-5 w-24 animate-pulse rounded bg-muted"
                                />
                                <div
                                    class="h-3 w-14 animate-pulse rounded bg-muted"
                                />
                            </div>
                        </div>
                    </div>
                </template>

                <div
                    class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <article
                        v-for="tour in suggestedTours"
                        :key="tour.id"
                        class="group relative flex flex-col rounded-2xl bg-card p-3 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg"
                    >
                        <Link
                            :href="`/tours/${tour.slug}`"
                            class="relative block aspect-[4/3] overflow-hidden rounded-xl"
                        >
                            <img
                                v-if="tour.cover_image_url"
                                :src="tour.cover_image_url"
                                :alt="tour.name"
                                class="size-full object-cover transition-transform duration-500 group-hover:scale-105"
                            />
                            <div
                                v-else
                                class="flex size-full items-center justify-center bg-muted"
                            >
                                <span class="text-xs text-muted-foreground"
                                    >Sin imagen</span
                                >
                            </div>
                        </Link>
                        <div
                            v-if="isAuthenticated"
                            class="absolute top-5 right-5 z-10"
                        >
                            <FavoriteButton
                                :tour-id="tour.id"
                                :initial-favorite="tour.is_favorite"
                                class="size-9 rounded-full border-0 bg-white/90 text-foreground shadow-sm backdrop-blur hover:bg-white"
                            />
                        </div>
                        <div class="flex flex-1 flex-col gap-1 px-1 pt-4 pb-2">
                            <Link
                                :href="`/tours/${tour.slug}`"
                                class="text-base leading-tight font-bold text-foreground transition group-hover:text-primary"
                            >
                                {{ tour.name }}
                            </Link>
                            <p
                                v-if="tour.category"
                                class="text-xs text-muted-foreground"
                            >
                                {{ tour.category.name }}
                            </p>
                            <div
                                class="mt-3 flex items-center justify-between gap-2"
                            >
                                <span
                                    class="text-base font-bold text-foreground"
                                >
                                    {{
                                        formatPrice(
                                            tour.base_price,
                                            tour.currency,
                                        )
                                    }}
                                </span>
                                <span
                                    v-if="tour.rating_count > 0"
                                    class="flex items-center gap-1 text-xs font-medium text-muted-foreground"
                                    :title="`${tour.rating_average} de 5`"
                                >
                                    <Star
                                        class="size-3.5 fill-amber-400 text-amber-400"
                                    />
                                    {{ Number(tour.rating_average).toFixed(1) }}
                                    <span class="text-muted-foreground/70"
                                        >({{ tour.rating_count }})</span
                                    >
                                </span>
                            </div>
                        </div>
                    </article>
                </div>
            </Deferred>
        </section>

        <!-- Promotions Section -->
        <section
            v-if="promotions === undefined || (promotions?.length ?? 0) > 0"
            class="mx-auto w-full max-w-7xl px-4 pb-14 sm:px-6 lg:px-8"
        >
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2
                        class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl"
                    >
                        Promociones especiales
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Aprovecha nuestras ofertas de temporada
                    </p>
                </div>
                <Link
                    :href="catalogIndex().url"
                    class="flex shrink-0 items-center gap-1 text-sm font-medium text-primary transition hover:underline"
                >
                    Ver todos
                    <ArrowRight class="size-4" />
                </Link>
            </div>

            <Deferred data="promotions">
                <template #fallback>
                    <div
                        class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <div
                            v-for="n in 3"
                            :key="`promo-skel-${n}`"
                            class="flex flex-col gap-3 rounded-2xl bg-card p-3 shadow-sm"
                        >
                            <div
                                class="aspect-[16/9] w-full animate-pulse rounded-xl bg-muted"
                            />
                            <div
                                class="mt-1 h-5 w-1/2 animate-pulse rounded bg-muted"
                            />
                            <div
                                class="h-3 w-full animate-pulse rounded bg-muted"
                            />
                            <div
                                class="mt-2 h-9 w-32 animate-pulse rounded-full bg-muted"
                            />
                        </div>
                    </div>
                </template>

                <div
                    v-if="(promotions?.length ?? 0) > 0"
                    class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <article
                        v-for="promo in promotions"
                        :key="promo.id"
                        class="group relative flex flex-col rounded-2xl bg-card p-3 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg"
                    >
                        <div
                            class="relative aspect-[16/9] overflow-hidden rounded-xl"
                        >
                            <img
                                v-if="promo.cover_image_url"
                                :src="promo.cover_image_url"
                                :alt="promo.name"
                                class="size-full object-cover transition-transform duration-500 group-hover:scale-105"
                            />
                            <div
                                v-else
                                class="flex size-full items-center justify-center bg-muted"
                            >
                                <span class="text-xs text-muted-foreground"
                                    >Sin imagen</span
                                >
                            </div>
                            <span
                                class="absolute top-3 left-3 rounded-full bg-destructive px-3 py-1 text-xs font-bold tracking-wide text-destructive-foreground shadow-sm"
                            >
                                {{ promo.discount_label }} OFF
                            </span>
                        </div>
                        <div class="flex flex-1 flex-col gap-2 px-2 pt-4 pb-2">
                            <h3 class="text-lg font-bold text-foreground">
                                {{ promo.name }}
                            </h3>
                            <p
                                v-if="promo.description"
                                class="line-clamp-2 text-sm text-muted-foreground"
                            >
                                {{ promo.description }}
                            </p>
                            <div class="mt-3 pt-1">
                                <Button
                                    v-if="promo.tour"
                                    as-child
                                    class="rounded-full px-6"
                                >
                                    <Link :href="`/tours/${promo.tour.slug}`">
                                        Comprar ahora
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </article>
                </div>
            </Deferred>
        </section>

        <!-- Newsletter Section -->
        <section class="mx-auto w-full max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
            <div
                class="relative overflow-hidden rounded-3xl bg-[#2B3B2E] px-6 py-16 shadow-sm sm:px-12"
            >
                <div
                    class="pointer-events-none absolute -top-1/2 left-1/2 size-[120%] -translate-x-1/2 rounded-full border border-white/5"
                    aria-hidden="true"
                />
                <div class="relative mx-auto max-w-xl text-center">
                    <h2
                        class="text-2xl font-bold tracking-tight text-white sm:text-3xl"
                    >
                        Mantente actualizado
                    </h2>
                    <p class="mx-auto mt-3 max-w-md text-sm text-white/70">
                        Recibe noticias, descuentos exclusivos y sugerencias
                        directo en tu bandeja.
                    </p>
                    <form
                        v-if="!newsletterSuccess"
                        class="mx-auto mt-8 flex w-full max-w-md items-center gap-2 rounded-full bg-white/10 p-1.5 ring-1 ring-white/15"
                        @submit.prevent="handleNewsletterSubscribe"
                    >
                        <label class="sr-only" for="newsletter-email"
                            >Correo electrónico</label
                        >
                        <input
                            id="newsletter-email"
                            v-model="newsletterEmail"
                            type="email"
                            required
                            placeholder="Tu correo electrónico"
                            class="min-w-0 flex-1 rounded-full border-0 bg-transparent px-4 py-2 text-sm text-white placeholder:text-white/50 focus:outline-none"
                        />
                        <Button
                            type="submit"
                            :disabled="newsletterSubmitting"
                            class="shrink-0 rounded-full bg-[#f5ecdc] px-6 text-[#2B3B2E] hover:bg-white"
                        >
                            Suscribirse
                        </Button>
                    </form>
                    <p
                        v-else
                        class="mt-8 text-sm font-medium text-emerald-300"
                        role="status"
                    >
                        Te has suscrito exitosamente. Revisa tu bandeja de
                        entrada.
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>
