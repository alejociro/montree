<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Sparkles, Star } from 'lucide-vue-next';
import { computed } from 'vue';
import { show as tourShow } from '@/actions/App/Http/Controllers/PublicTourPageController';
import FavoriteButton from '@/components/molecules/FavoriteButton.vue';
import { categoryLabel } from '@/lib/categories';
import { formatCurrency } from '@/lib/format';
import type { CatalogTour } from '@/types/catalog';

type Props = {
    tour: CatalogTour;
    isAuthenticated?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    isAuthenticated: false,
});

const href = computed(() => tourShow.url(props.tour.slug));
const hasRating = computed(() => props.tour.rating_count > 0);
</script>

<template>
    <article
        class="group relative flex h-full flex-col overflow-hidden rounded-2xl bg-card shadow-sm ring-1 ring-border/50 transition duration-300 focus-within:shadow-xl hover:-translate-y-1 hover:shadow-xl"
    >
        <Link
            :href="href"
            class="relative block aspect-[4/3] overflow-hidden bg-muted"
        >
            <img
                v-if="tour.cover_image_url"
                :src="tour.cover_image_url"
                :alt="tour.name"
                loading="lazy"
                class="size-full object-cover transition-transform duration-500 ease-out group-hover:scale-110"
            />
            <div
                v-else
                class="flex size-full items-center justify-center text-xs tracking-wide text-muted-foreground uppercase"
            >
                {{ $t('Sin imagen') }}
            </div>

            <div
                class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/35 via-transparent to-transparent opacity-70 transition group-hover:opacity-90"
                aria-hidden="true"
            />

            <span
                v-if="tour.category"
                class="absolute top-3 left-3 rounded-full bg-background/85 px-2.5 py-1 text-[11px] font-semibold text-foreground shadow-sm backdrop-blur"
            >
                {{ categoryLabel(tour.category.name) }}
            </span>

            <span
                v-if="!hasRating"
                class="absolute bottom-3 left-3 inline-flex items-center gap-1 rounded-full bg-primary px-2.5 py-1 text-[11px] font-semibold text-primary-foreground shadow-sm"
            >
                <Sparkles class="size-3" aria-hidden="true" />
                {{ $t('Nuevo') }}
            </span>
        </Link>

        <div v-if="isAuthenticated" class="absolute top-3 right-3 z-10">
            <FavoriteButton
                :tour-id="tour.id"
                :initial-favorite="tour.is_favorite"
                class="size-9 rounded-full border-0 bg-background/90 text-foreground shadow-sm backdrop-blur hover:bg-background"
            />
        </div>

        <div class="flex flex-1 flex-col gap-2 p-4">
            <div class="flex items-start justify-between gap-2">
                <Link
                    :href="href"
                    class="line-clamp-2 text-sm leading-snug font-bold tracking-tight text-foreground transition group-hover:text-primary"
                >
                    {{ tour.name }}
                </Link>
                <span
                    v-if="hasRating"
                    class="flex shrink-0 items-center gap-1 text-xs font-semibold text-foreground"
                    :title="`${tour.rating_average} de 5`"
                >
                    <Star class="size-3.5 fill-primary text-primary" />
                    {{ Number(tour.rating_average).toFixed(1) }}
                    <span class="font-normal text-muted-foreground"
                        >({{ tour.rating_count }})</span
                    >
                </span>
            </div>

            <p
                v-if="tour.short_description"
                class="line-clamp-2 text-xs text-muted-foreground"
            >
                {{ tour.short_description }}
            </p>

            <p class="mt-auto pt-2 text-sm text-muted-foreground">
                {{ $t('Desde') }}
                <span class="text-base font-bold text-foreground">
                    {{ formatCurrency(tour.base_price, tour.currency) }}
                </span>
                <span class="text-xs">{{ $t('/persona') }}</span>
            </p>
        </div>
    </article>
</template>
