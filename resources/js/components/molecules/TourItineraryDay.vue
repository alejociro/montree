<script setup lang="ts">
import { MapPinned } from 'lucide-vue-next';
import { computed } from 'vue';
import type { TourDetailItineraryStep } from '@/types/tour-detail';

const props = defineProps<{
    step: TourDetailItineraryStep;
    /** Solo hay botón al mapa cuando el paso tiene una parada asociada. */
    mappable: boolean;
}>();

const emit = defineEmits<{
    (e: 'show-on-map'): void;
}>();

const number = computed(() => String(props.step.step_number).padStart(2, '0'));
</script>

<template>
    <div
        class="mb-2 grid grid-cols-[64px_1fr] items-start gap-4 rounded-xl border border-border bg-card px-3.5 py-4 sm:grid-cols-[64px_1fr_auto]"
    >
        <p class="text-[11px] tracking-[0.08em] text-primary uppercase">
            {{ $t('Paso :number', { number }) }}
        </p>
        <div class="min-w-0">
            <h3 class="text-[15.5px] font-semibold">{{ step.title }}</h3>
            <p
                v-if="step.duration_label"
                class="mt-1 text-[11px] tracking-[0.08em] text-muted-foreground uppercase"
            >
                {{ step.duration_label }}
            </p>
            <p
                v-if="step.description"
                class="mt-1 text-[13.5px] text-muted-foreground"
            >
                {{ step.description }}
            </p>
        </div>
        <button
            v-if="mappable"
            type="button"
            class="col-start-2 flex items-center gap-1.5 justify-self-start rounded-full border border-border px-3.5 py-1.5 text-[13px] font-medium whitespace-nowrap text-primary transition hover:border-brand-green-100 hover:bg-brand-green-100 sm:col-start-3 sm:self-center"
            @click="emit('show-on-map')"
        >
            <MapPinned class="size-3.5" />
            {{ $t('Ver en el mapa') }}
        </button>
    </div>
</template>
