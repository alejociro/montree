<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { show as tourShow } from '@/actions/App/Http/Controllers/PublicTourPageController';
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
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <template v-if="loading">
            <div
                v-for="index in skeletonCount"
                :key="`skeleton-${index}`"
                class="flex flex-col gap-3 overflow-hidden rounded-xl bg-card shadow-sm"
                data-testid="tour-grid-skeleton"
            >
                <Skeleton class="aspect-[3/4] w-full rounded-none" />
                <div class="flex flex-col gap-2 p-3">
                    <Skeleton class="h-4 w-3/4" />
                    <Skeleton class="h-3 w-full" />
                    <Skeleton class="mt-2 h-4 w-24" />
                </div>
            </div>
        </template>
        <template v-else>
            <Link
                v-for="tour in tours"
                :key="tour.id"
                :href="tourShow(tour.slug).url"
                class="block h-full"
            >
                <TourCard :tour="tour" />
            </Link>
        </template>
    </div>
</template>
