<script setup lang="ts">
import { computed } from 'vue';
import { routeColor } from '@/lib/tour-route';
import type { TourDepartureRouteStop } from '@/types/tour-detail';

const props = defineProps<{
    title: string;
    stops: TourDepartureRouteStop[];
}>();

const orderedStops = computed(() =>
    [...props.stops].sort((one, other) => one.position - other.position),
);
</script>

<template>
    <div class="rounded-2xl border border-border bg-card px-4.5 py-4">
        <p
            class="text-[11px] tracking-[0.08em] text-muted-foreground uppercase"
        >
            {{ title }}
        </p>
        <ul>
            <li
                v-for="stop in orderedStops"
                :key="stop.position"
                class="flex gap-3 border-t border-border py-2.5 text-[13.5px] first:border-t-0"
            >
                <span
                    class="mt-1.5 size-2.5 flex-none rounded-full"
                    :style="{ background: routeColor(stop.kind) }"
                    aria-hidden="true"
                />
                <span class="min-w-0">
                    <span class="block font-semibold">
                        <template v-if="stop.time_label"
                            >{{ stop.time_label }} — </template
                        >{{ stop.name }}
                    </span>
                </span>
            </li>
        </ul>
    </div>
</template>
