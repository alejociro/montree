<script setup lang="ts">
import { stopColor } from '@/lib/tour-route';
import type { TourRouteStop } from '@/types/tour-route';

defineProps<{
    stops: TourRouteStop[];
}>();

const emit = defineEmits<{
    (e: 'select', index: number): void;
}>();
</script>

<template>
    <div class="rounded-2xl border border-border bg-card px-4.5 py-4">
        <p
            class="text-[11px] tracking-[0.08em] text-muted-foreground uppercase"
        >
            {{ $t('Logística del día') }}
        </p>
        <button
            v-for="(stop, index) in stops"
            :key="`${stop.code}-${index}`"
            type="button"
            class="flex w-full gap-3 border-t border-border py-2.5 text-left text-[13.5px] transition first:border-t-0 hover:text-primary"
            @click="emit('select', index)"
        >
            <span
                class="mt-1.5 size-2.5 flex-none rounded-full"
                :style="{ background: stopColor(stop) }"
                aria-hidden="true"
            />
            <span class="min-w-0">
                <span class="block font-semibold">
                    <template v-if="stop.time">{{ stop.time }} — </template
                    >{{ stop.label ?? stop.name }}
                </span>
                <span class="block truncate text-muted-foreground">
                    {{ stop.place ?? stop.name }}
                </span>
            </span>
        </button>
    </div>
</template>
