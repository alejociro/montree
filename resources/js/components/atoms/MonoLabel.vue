<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

/**
 * «Etiqueta mono» del handoff, resuelta sobre Instrument Sans (D6): el handoff
 * pedía IBM Plex Mono y el proyecto ya decidió no sumar familias. Mayúsculas +
 * `letter-spacing` dan el mismo efecto —etiqueta técnica, discreta— sin ~180 kB
 * de fuentes.
 */
type Props = {
    as?: 'span' | 'dt' | 'p' | 'th';
    class?: string;
};

const props = withDefaults(defineProps<Props>(), { as: 'span', class: '' });

const classes = computed(() =>
    cn(
        'block text-[10.5px] leading-none font-semibold tracking-[0.09em] text-muted-foreground uppercase',
        props.class,
    ),
);
</script>

<template>
    <component :is="props.as" :class="classes">
        <slot />
    </component>
</template>
