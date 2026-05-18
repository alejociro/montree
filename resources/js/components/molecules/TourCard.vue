<script setup lang="ts">
import { Calendar, Clock, Heart, MapPin, Star, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardFooter } from '@/components/ui/card';
import { formatCurrency, formatDateTime } from '@/lib/format';
import type { CatalogTour } from '@/types/catalog';
import type { TourDifficulty } from '@/types/tour';

type Props = {
    tour: CatalogTour;
};

const props = defineProps<Props>();

const difficultyLabel: Record<TourDifficulty, string> = {
    easy: 'Fácil',
    moderate: 'Moderado',
    hard: 'Difícil',
    extreme: 'Extremo',
};

const difficultyClasses: Record<TourDifficulty, string> = {
    easy: 'bg-emerald-100 text-emerald-900 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-100',
    moderate:
        'bg-amber-100 text-amber-900 border-amber-200 dark:bg-amber-900/30 dark:text-amber-100',
    hard: 'bg-orange-100 text-orange-900 border-orange-200 dark:bg-orange-900/30 dark:text-orange-100',
    extreme:
        'bg-rose-100 text-rose-900 border-rose-200 dark:bg-rose-900/30 dark:text-rose-100',
};

const ratingLabel = computed(() => {
    const value = Number.parseFloat(props.tour.rating_average);

    if (!Number.isFinite(value) || props.tour.rating_count === 0) {
        return 'Nuevo';
    }

    return value.toFixed(1);
});
</script>

<template>
    <Card
        class="group flex h-full flex-col overflow-hidden transition focus-within:shadow-md hover:shadow-md"
    >
        <div
            class="relative aspect-[4/3] w-full overflow-hidden bg-muted text-muted-foreground"
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
                Sin imagen
            </div>

            <Badge
                v-if="tour.is_favorite"
                variant="default"
                class="absolute top-3 right-3 gap-1 bg-background/90 text-foreground"
            >
                <Heart class="fill-current text-rose-500" /> Favorito
            </Badge>

            <Badge
                v-if="!tour.has_future_dates"
                variant="outline"
                class="absolute bottom-3 left-3 bg-background/90"
            >
                Sin disponibilidad
            </Badge>
        </div>

        <CardContent class="flex flex-1 flex-col gap-3 p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <span
                        v-if="tour.category"
                        class="text-xs font-medium text-muted-foreground"
                    >
                        {{ tour.category.name }}
                    </span>
                    <h3
                        class="line-clamp-2 text-base font-semibold tracking-tight text-foreground"
                    >
                        {{ tour.name }}
                    </h3>
                </div>
                <div
                    class="flex items-center gap-1 rounded-md bg-muted px-2 py-1 text-xs font-medium"
                    :aria-label="`Rating ${ratingLabel}`"
                >
                    <Star class="size-3.5 fill-amber-400 text-amber-400" />
                    <span>{{ ratingLabel }}</span>
                </div>
            </div>

            <p
                v-if="tour.short_description"
                class="line-clamp-2 text-sm text-muted-foreground"
            >
                {{ tour.short_description }}
            </p>

            <div
                class="grid grid-cols-2 gap-x-3 gap-y-2 text-xs text-muted-foreground"
            >
                <span class="flex items-center gap-1.5">
                    <Clock class="size-3.5" />
                    {{ tour.duration_hours }} h
                </span>
                <span class="flex items-center gap-1.5">
                    <Users class="size-3.5" />
                    Hasta {{ tour.default_capacity }}
                </span>
                <Badge
                    variant="outline"
                    :class="difficultyClasses[tour.difficulty]"
                >
                    {{ difficultyLabel[tour.difficulty] }}
                </Badge>
                <span
                    v-if="tour.next_date_starts_at"
                    class="flex items-center gap-1.5"
                >
                    <Calendar class="size-3.5" />
                    {{ formatDateTime(tour.next_date_starts_at) }}
                </span>
                <span v-else class="flex items-center gap-1.5">
                    <MapPin class="size-3.5" />
                    Próximamente
                </span>
            </div>
        </CardContent>

        <CardFooter
            class="flex items-baseline justify-between border-t border-border/60 bg-card/50 px-4 py-3"
        >
            <span class="text-xs text-muted-foreground">Desde</span>
            <span class="text-base font-semibold text-foreground">
                {{ formatCurrency(tour.base_price, tour.currency) }}
            </span>
        </CardFooter>
    </Card>
</template>
