<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import { stopColor } from '@/lib/tour-route';
import type { TourRouteStop } from '@/types/tour-route';

const props = defineProps<{
    stops: TourRouteStop[];
    selectedIndex: number | null;
}>();

const emit = defineEmits<{
    (e: 'select', index: number): void;
}>();

const listElement = ref<HTMLElement | null>(null);

watch(
    () => props.selectedIndex,
    async (index) => {
        if (index === null) {
            return;
        }

        await nextTick();
        listElement.value
            ?.querySelector(`[data-stop-index="${index}"]`)
            ?.scrollIntoView({ block: 'nearest' });
    },
);
</script>

<template>
    <div
        ref="listElement"
        class="max-h-[260px] overflow-y-auto border-b border-border lg:max-h-[470px] lg:border-r lg:border-b-0"
    >
        <button
            v-for="(stop, index) in stops"
            :key="`${stop.code}-${index}`"
            type="button"
            :data-stop-index="index"
            :aria-current="index === selectedIndex ? 'true' : undefined"
            class="grid w-full grid-cols-[30px_1fr] gap-3 border-b border-l-[3px] border-border px-4 py-3.5 text-left transition last:border-b-0"
            :class="
                index === selectedIndex
                    ? 'border-l-primary bg-secondary'
                    : 'border-l-transparent hover:bg-secondary/60'
            "
            @click="emit('select', index)"
        >
            <span
                class="mt-0.5 grid size-[26px] place-items-center rounded-full text-xs font-bold text-white"
                :style="{ background: stopColor(stop) }"
            >
                {{ stop.code }}
            </span>
            <span class="min-w-0">
                <span
                    v-if="stop.time"
                    class="block text-[10.5px] tracking-[0.08em] text-muted-foreground uppercase"
                >
                    {{ stop.time }}
                </span>
                <span class="block truncate text-sm font-semibold">{{
                    stop.name
                }}</span>
                <span
                    v-if="stop.place"
                    class="block truncate text-xs text-muted-foreground"
                >
                    {{ stop.place }}
                </span>
            </span>
        </button>
    </div>
</template>
