<script setup lang="ts">
import { Heart } from 'lucide-vue-next';
import { formatCurrency } from '@/lib/format';
import type { CatalogTour } from '@/types/catalog';

type Props = {
    tour: CatalogTour;
};

defineProps<Props>();
</script>

<template>
    <article
        class="group flex h-full flex-col overflow-hidden rounded-xl bg-card shadow-sm transition focus-within:shadow-md hover:shadow-md"
    >
        <div
            class="relative aspect-[3/4] w-full overflow-hidden bg-muted text-muted-foreground"
        >
            <img
                v-if="tour.cover_image_url"
                :src="tour.cover_image_url"
                :alt="tour.name"
                class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                loading="lazy"
            />
            <div
                v-else
                class="flex h-full w-full items-center justify-center text-xs tracking-wide uppercase"
            >
                {{ $t('Sin imagen') }}
            </div>

            <span
                v-if="tour.is_favorite"
                class="absolute top-2 left-2 rounded-md bg-primary px-2 py-1 text-[11px] font-semibold tracking-wide text-primary-foreground shadow-sm"
            >
                {{ $t('Destacado') }}
            </span>

            <button
                type="button"
                class="absolute top-2 right-2 flex size-8 items-center justify-center rounded-full bg-background/80 text-foreground backdrop-blur transition hover:bg-background focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
                :aria-pressed="tour.is_favorite"
                :aria-label="
                    tour.is_favorite
                        ? $t('Quitar de favoritos')
                        : $t('Agregar a favoritos')
                "
            >
                <Heart
                    class="size-4"
                    :class="
                        tour.is_favorite
                            ? 'fill-primary text-primary'
                            : 'text-foreground'
                    "
                />
            </button>

            <span
                v-if="!tour.has_future_dates"
                class="absolute bottom-2 left-2 rounded-md bg-background/90 px-2 py-1 text-[11px] font-medium text-muted-foreground shadow-sm"
            >
                {{ $t('Sin disponibilidad') }}
            </span>
        </div>

        <div class="flex flex-1 flex-col gap-1.5 p-3">
            <h3
                class="line-clamp-2 text-sm font-semibold tracking-tight text-foreground"
            >
                {{ tour.name }}
            </h3>

            <p
                v-if="tour.short_description"
                class="line-clamp-2 text-xs text-muted-foreground"
            >
                {{ tour.short_description }}
            </p>

            <p class="mt-auto pt-2 text-sm font-semibold text-foreground">
                {{ formatCurrency(tour.base_price, tour.currency) }}
                <span class="text-xs font-normal text-muted-foreground">
                    {{ $t('/persona') }}
                </span>
            </p>
        </div>
    </article>
</template>
