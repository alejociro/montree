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

/**
 * WHY `table-cell`: la etiqueta nace `block` para que se apile bajo el dato al
 * que rotula. Dentro de un `<th>` ese mismo `block` saca la celda del flujo de
 * la tabla y la cabecera se dibuja en vertical, una palabra por línea —así se
 * veían Salidas, Promociones y Newsletter—.
 */
const classes = computed(() =>
    cn(
        props.as === 'th' ? 'table-cell' : 'block',
        'text-[10.5px] leading-none font-semibold tracking-[0.09em] text-muted-foreground uppercase',
        props.class,
    ),
);
</script>

<template>
    <component :is="props.as" :class="classes">
        <slot />
    </component>
</template>
