<script setup lang="ts">
import { Deferred, Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, MapPin, Mountain, Search, Star, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FavoriteButton from '@/components/molecules/FavoriteButton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTenant } from '@/composables/useTenant';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { register } from '@/routes';
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

function renderStars(average: string): number {
    return Math.round(Number(average));
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
    <Head :title="displayName" />

    <!-- Hero Section -->
    <section class="relative overflow-hidden">
        <div v-if="configuration?.hero_image_url" class="absolute inset-0">
            <img :src="configuration.hero_image_url" alt="" class="size-full object-cover" />
            <div class="absolute inset-0 bg-black/50" />
        </div>
        <div v-else class="absolute inset-0 bg-gradient-to-br from-primary/95 via-primary/80 to-primary/60" />
        <div
            class="relative z-10 mx-auto w-full max-w-7xl px-4 py-20 sm:px-6 sm:py-28 lg:px-8 lg:py-36"
        >
            <div class="max-w-2xl">
                <h1
                    class="text-4xl font-bold tracking-tight text-primary-foreground sm:text-5xl lg:text-6xl"
                >
                    {{ configuration?.tagline || 'Encuentra tu próxima aventura' }}
                </h1>
                <p
                    class="mt-4 text-lg text-primary-foreground/80 sm:text-xl"
                >
                    {{ configuration?.description || 'Explora el mundo con nosotros' }}
                </p>
                <form
                    class="mt-8 flex w-full max-w-lg gap-2"
                    @submit.prevent="handleSearch"
                >
                    <div class="relative flex-1">
                        <Search
                            class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Buscar tours, experiencias..."
                            class="bg-background pl-9"
                        />
                    </div>
                    <Button type="submit">
                        Buscar
                        <ArrowUpRight class="ml-1 size-4" />
                    </Button>
                </form>
            </div>
        </div>
    </section>

    <!-- Featured Tours Section -->
    <section class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="flex items-end justify-between">
            <div>
                <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                    Tours
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Nuestras experiencias más reservadas este mes
                </p>
            </div>
            <Link
                :href="catalogIndex().url"
                class="flex items-center gap-1 text-sm font-medium text-primary transition hover:underline"
            >
                Ver todos
                <ArrowUpRight class="size-4" />
            </Link>
        </div>

        <Deferred data="featuredTours">
            <template #fallback>
                <div
                    class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div
                        v-for="n in 4"
                        :key="`ft-skel-${n}`"
                        class="flex flex-col gap-3 rounded-xl border border-border bg-card p-3"
                    >
                        <div class="aspect-[4/3] w-full animate-pulse rounded-lg bg-muted" />
                        <div class="h-4 w-2/3 animate-pulse rounded bg-muted" />
                        <div class="h-3 w-full animate-pulse rounded bg-muted" />
                        <div class="flex gap-2">
                            <div class="h-3 w-16 animate-pulse rounded bg-muted" />
                            <div class="h-3 w-12 animate-pulse rounded bg-muted" />
                        </div>
                        <div class="mt-2 h-5 w-24 animate-pulse rounded bg-muted" />
                    </div>
                </div>
            </template>

            <div
                class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
            >
                <article
                    v-for="tour in featuredTours"
                    :key="tour.id"
                    class="group relative flex flex-col overflow-hidden rounded-xl border border-border bg-card shadow-sm transition hover:shadow-md"
                >
                    <Link
                        :href="`/tours/${tour.slug}`"
                        class="relative aspect-[4/3] overflow-hidden"
                    >
                        <img
                            v-if="tour.cover_image_url"
                            :src="tour.cover_image_url"
                            :alt="tour.name"
                            class="size-full object-cover transition-transform duration-300 group-hover:scale-105"
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
                        class="absolute top-2 right-2 z-10"
                    >
                        <FavoriteButton
                            :tour-id="tour.id"
                            :initial-favorite="tour.is_favorite"
                        />
                    </div>
                    <div class="flex flex-1 flex-col gap-2 p-3">
                        <Link
                            :href="`/tours/${tour.slug}`"
                            class="text-sm font-semibold leading-tight text-foreground transition hover:text-primary"
                        >
                            {{ tour.name }}
                        </Link>
                        <Badge
                            v-if="tour.category"
                            variant="secondary"
                            class="w-fit text-xs"
                        >
                            {{ tour.category.name }}
                        </Badge>
                        <div class="mt-auto flex items-center justify-between">
                            <span class="text-base font-bold text-foreground">
                                {{
                                    formatPrice(
                                        tour.base_price,
                                        tour.currency,
                                    )
                                }}
                            </span>
                            <div
                                class="flex items-center gap-0.5"
                                :title="`${tour.rating_average} de 5 (${tour.rating_count})`"
                            >
                                <Star
                                    v-for="s in 5"
                                    :key="s"
                                    :class="[
                                        'size-3.5',
                                        s <= renderStars(tour.rating_average)
                                            ? 'fill-amber-400 text-amber-400'
                                            : 'text-muted-foreground/40',
                                    ]"
                                />
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </Deferred>
    </section>

    <!-- Suggested Tours Section -->
    <section
        class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8"
    >
        <div class="flex items-end justify-between">
            <div>
                <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                    Sugerencias para ti
                </h2>
            </div>
            <Link
                :href="catalogIndex().url"
                class="flex items-center gap-1 text-sm font-medium text-primary transition hover:underline"
            >
                Ver todos
                <ArrowUpRight class="size-4" />
            </Link>
        </div>

        <Deferred data="suggestedTours">
            <template #fallback>
                <div
                    class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div
                        v-for="n in 4"
                        :key="`sg-skel-${n}`"
                        class="flex flex-col gap-3 rounded-xl border border-border bg-card p-3"
                    >
                        <div class="aspect-[4/3] w-full animate-pulse rounded-lg bg-muted" />
                        <div class="h-4 w-2/3 animate-pulse rounded bg-muted" />
                        <div class="h-3 w-full animate-pulse rounded bg-muted" />
                        <div class="flex gap-2">
                            <div class="h-3 w-16 animate-pulse rounded bg-muted" />
                            <div class="h-3 w-12 animate-pulse rounded bg-muted" />
                        </div>
                        <div class="mt-2 h-5 w-24 animate-pulse rounded bg-muted" />
                    </div>
                </div>
            </template>

            <div
                class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
            >
                <article
                    v-for="tour in suggestedTours"
                    :key="tour.id"
                    class="group relative flex flex-col overflow-hidden rounded-xl border border-border bg-card shadow-sm transition hover:shadow-md"
                >
                    <Link
                        :href="`/tours/${tour.slug}`"
                        class="relative aspect-[4/3] overflow-hidden"
                    >
                        <img
                            v-if="tour.cover_image_url"
                            :src="tour.cover_image_url"
                            :alt="tour.name"
                            class="size-full object-cover transition-transform duration-300 group-hover:scale-105"
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
                        class="absolute top-2 right-2 z-10"
                    >
                        <FavoriteButton
                            :tour-id="tour.id"
                            :initial-favorite="tour.is_favorite"
                        />
                    </div>
                    <div class="flex flex-1 flex-col gap-2 p-3">
                        <Link
                            :href="`/tours/${tour.slug}`"
                            class="text-sm font-semibold leading-tight text-foreground transition hover:text-primary"
                        >
                            {{ tour.name }}
                        </Link>
                        <Badge
                            v-if="tour.category"
                            variant="secondary"
                            class="w-fit text-xs"
                        >
                            {{ tour.category.name }}
                        </Badge>
                        <div class="mt-auto flex items-center justify-between">
                            <span class="text-base font-bold text-foreground">
                                {{
                                    formatPrice(
                                        tour.base_price,
                                        tour.currency,
                                    )
                                }}
                            </span>
                            <div
                                class="flex items-center gap-0.5"
                                :title="`${tour.rating_average} de 5 (${tour.rating_count})`"
                            >
                                <Star
                                    v-for="s in 5"
                                    :key="s"
                                    :class="[
                                        'size-3.5',
                                        s <= renderStars(tour.rating_average)
                                            ? 'fill-amber-400 text-amber-400'
                                            : 'text-muted-foreground/40',
                                    ]"
                                />
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </Deferred>
    </section>

    <!-- Promotions Section -->
    <section class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="flex items-end justify-between">
            <div>
                <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                    Promociones especiales
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Aprovecha nuestras ofertas de temporada
                </p>
            </div>
            <Link
                :href="catalogIndex().url"
                class="flex items-center gap-1 text-sm font-medium text-primary transition hover:underline"
            >
                Ver todos
                <ArrowUpRight class="size-4" />
            </Link>
        </div>

        <Deferred data="promotions">
            <template #fallback>
                <div
                    class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div
                        v-for="n in 3"
                        :key="`promo-skel-${n}`"
                        class="flex flex-col gap-3 rounded-xl border border-border bg-card p-3"
                    >
                        <div class="aspect-[16/9] w-full animate-pulse rounded-lg bg-muted" />
                        <div class="h-5 w-1/2 animate-pulse rounded bg-muted" />
                        <div class="h-3 w-full animate-pulse rounded bg-muted" />
                        <div class="mt-2 h-9 w-32 animate-pulse rounded bg-muted" />
                    </div>
                </div>
            </template>

            <div
                v-if="(promotions?.length ?? 0) > 0"
                class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                <article
                    v-for="promo in promotions"
                    :key="promo.id"
                    class="group relative flex flex-col overflow-hidden rounded-xl border border-border bg-card shadow-sm transition hover:shadow-md"
                >
                    <div class="relative aspect-[16/9] overflow-hidden">
                        <img
                            v-if="promo.cover_image_url"
                            :src="promo.cover_image_url"
                            :alt="promo.name"
                            class="size-full object-cover transition-transform duration-300 group-hover:scale-105"
                        />
                        <div
                            v-else
                            class="flex size-full items-center justify-center bg-muted"
                        >
                            <span class="text-xs text-muted-foreground"
                                >Sin imagen</span
                            >
                        </div>
                        <Badge
                            class="absolute top-2 left-2 bg-destructive text-destructive-foreground"
                        >
                            {{ promo.discount_label }} OFF
                        </Badge>
                    </div>
                    <div class="flex flex-1 flex-col gap-2 p-4">
                        <h3 class="text-base font-semibold text-foreground">
                            {{ promo.name }}
                        </h3>
                        <p
                            v-if="promo.description"
                            class="text-sm text-muted-foreground line-clamp-2"
                        >
                            {{ promo.description }}
                        </p>
                        <div class="mt-auto pt-2">
                            <Button
                                v-if="promo.tour"
                                as-child
                                size="sm"
                            >
                                <Link
                                    :href="`/tours/${promo.tour.slug}`"
                                >
                                    Comprar ahora
                                </Link>
                            </Button>
                        </div>
                    </div>
                </article>
            </div>

            <div
                v-else
                class="mt-6 rounded-xl border border-dashed border-border bg-card p-10 text-center"
            >
                <p class="text-sm text-muted-foreground">
                    No hay promociones activas en este momento.
                </p>
            </div>
        </Deferred>
    </section>

    <!-- Community / Social Proof Section -->
    <section class="border-y border-border/60 bg-card/50">
        <div class="mx-auto w-full max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <div class="text-center">
                <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                    Únete a nuestra comunidad
                </h2>
                <p class="mx-auto mt-3 max-w-2xl text-sm text-muted-foreground sm:text-base">
                    Miles de viajeros ya han descubierto experiencias únicas con {{ displayName }}.
                    Sé parte de nuestra comunidad y vive aventuras que recordarás siempre.
                </p>
            </div>

            <div class="mx-auto mt-10 grid max-w-3xl grid-cols-1 gap-6 sm:grid-cols-3">
                <div class="flex flex-col items-center gap-2 rounded-xl border border-border bg-card p-6 text-center shadow-sm">
                    <div class="flex size-12 items-center justify-center rounded-full bg-primary/10">
                        <Users class="size-6 text-primary" />
                    </div>
                    <span class="text-3xl font-bold tracking-tight text-foreground">500+</span>
                    <span class="text-sm text-muted-foreground">Viajeros felices</span>
                </div>
                <div class="flex flex-col items-center gap-2 rounded-xl border border-border bg-card p-6 text-center shadow-sm">
                    <div class="flex size-12 items-center justify-center rounded-full bg-primary/10">
                        <Mountain class="size-6 text-primary" />
                    </div>
                    <span class="text-3xl font-bold tracking-tight text-foreground">50+</span>
                    <span class="text-sm text-muted-foreground">Experiencias únicas</span>
                </div>
                <div class="flex flex-col items-center gap-2 rounded-xl border border-border bg-card p-6 text-center shadow-sm">
                    <div class="flex size-12 items-center justify-center rounded-full bg-primary/10">
                        <MapPin class="size-6 text-primary" />
                    </div>
                    <span class="text-3xl font-bold tracking-tight text-foreground">20+</span>
                    <span class="text-sm text-muted-foreground">Destinos por descubrir</span>
                </div>
            </div>

            <div
                v-if="!isAuthenticated"
                class="mx-auto mt-10 max-w-lg rounded-xl bg-primary/5 p-6 text-center sm:p-8"
            >
                <h3 class="text-lg font-semibold text-foreground">
                    Creá tu cuenta gratis
                </h3>
                <p class="mt-2 text-sm text-muted-foreground">
                    Registrate para guardar favoritos, recibir recomendaciones personalizadas
                    y reservar con descuentos exclusivos.
                </p>
                <div class="mt-4 flex justify-center gap-3">
                    <Button as-child size="lg">
                        <Link :href="register().url">
                            Registrarse gratis
                        </Link>
                    </Button>
                    <Button as-child variant="outline" size="lg">
                        <Link :href="catalogIndex().url">
                            Explorar tours
                        </Link>
                    </Button>
                </div>
            </div>
        </div>
    </section>

    <!-- Newsletter Section -->
    <section class="bg-[#2B3B2E]">
        <div
            class="mx-auto w-full max-w-7xl px-4 py-16 sm:px-6 lg:px-8"
        >
            <div class="mx-auto max-w-xl text-center">
                <h2
                    class="text-2xl font-semibold tracking-tight text-white sm:text-3xl"
                >
                    Mantente actualizado
                </h2>
                <p class="mt-3 text-sm text-white/70">
                    Recibe noticias, descuentos exclusivos y sugerencias
                    directo en tu bandeja.
                </p>
                <form
                    v-if="!newsletterSuccess"
                    class="mt-6 flex gap-2"
                    @submit.prevent="handleNewsletterSubscribe"
                >
                    <Input
                        v-model="newsletterEmail"
                        type="email"
                        required
                        placeholder="Tu correo electrónico"
                        class="flex-1 bg-white/10 text-white placeholder:text-white/50 focus-visible:ring-white/30"
                    />
                    <Button
                        type="submit"
                        :disabled="newsletterSubmitting"
                        class="bg-white text-[#2B3B2E] hover:bg-white/90"
                    >
                        Suscribirse
                    </Button>
                </form>
                <p
                    v-else
                    class="mt-6 text-sm font-medium text-emerald-300"
                >
                    Te has suscrito exitosamente. Revisa tu bandeja de
                    entrada.
                </p>
            </div>
        </div>
    </section>
</template>
