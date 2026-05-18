<script setup lang="ts">
import TourCard from '@/components/molecules/TourCard.vue';
import { Skeleton } from '@/components/ui/skeleton';
import type { CatalogTour } from '@/types/catalog';

type Props = {
    tours: CatalogTour[];
    loading?: boolean;
    skeletonCount?: number;
};

withDefaults(defineProps<Props>(), {
    loading: false,
    skeletonCount: 6,
});
</script>

<template>
    <div
        class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
    >
        <template v-if="loading">
            <div
                v-for="index in skeletonCount"
                :key="`skeleton-${index}`"
                class="flex flex-col gap-3 rounded-xl border border-border bg-card p-3"
                data-testid="tour-grid-skeleton"
            >
                <Skeleton class="aspect-[4/3] w-full rounded-lg" />
                <Skeleton class="h-4 w-2/3" />
                <Skeleton class="h-3 w-full" />
                <div class="flex gap-2">
                    <Skeleton class="h-3 w-16" />
                    <Skeleton class="h-3 w-12" />
                </div>
                <Skeleton class="mt-2 h-5 w-24" />
            </div>
        </template>
        <template v-else>
            <TourCard v-for="tour in tours" :key="tour.id" :tour="tour" />
        </template>
    </div>
</template>
