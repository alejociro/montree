<script setup lang="ts">
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { cn } from '@/lib/utils';

const { t } = useTranslations();

/**
 * Barra de ocupación reutilizable (listado, edición y detalle del tour).
 *
 * WHY: la barra va por `--primary` —el color del tenant— y no por los tokens
 * semánticos (D5). Llenarse no es un estado de alerta: es la meta comercial.
 */
type Props = {
    occupied: number;
    capacity: number;
    /** Texto a la izquierda; sin él solo se dibuja la barra. */
    label?: string;
    /** Oculta el «9/16 · 56 %» de la derecha. */
    hideValue?: boolean;
    size?: 'sm' | 'md';
    class?: string;
};

const props = withDefaults(defineProps<Props>(), {
    label: undefined,
    hideValue: false,
    size: 'md',
    class: '',
});

const percent = computed<number>(() => {
    if (props.capacity <= 0) {
        return 0;
    }

    return Math.min(100, Math.round((props.occupied / props.capacity) * 100));
});

const valueLabel = computed<string>(() =>
    t(':occupied/:capacity · :percent %', {
        occupied: props.occupied,
        capacity: props.capacity,
        percent: percent.value,
    }),
);
</script>

<template>
    <div :class="cn('w-full', props.class)">
        <div
            v-if="props.label || !props.hideValue"
            class="mb-1.5 flex items-baseline justify-between gap-3 text-xs text-muted-foreground"
        >
            <span v-if="props.label" class="truncate">{{ props.label }}</span>
            <span
                v-if="!props.hideValue"
                class="font-semibold text-foreground tabular-nums"
            >
                {{ valueLabel }}
            </span>
        </div>

        <div
            class="overflow-hidden rounded-full bg-muted"
            :class="props.size === 'sm' ? 'h-1.5' : 'h-2'"
            role="progressbar"
            :aria-valuemin="0"
            :aria-valuemax="props.capacity"
            :aria-valuenow="props.occupied"
            :aria-label="props.label ?? $t('Ocupación')"
            :aria-valuetext="valueLabel"
        >
            <div
                class="h-full rounded-full bg-primary transition-all"
                :style="{ width: `${percent}%` }"
            />
        </div>
    </div>
</template>
