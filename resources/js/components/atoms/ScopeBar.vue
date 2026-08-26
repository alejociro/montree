<script setup lang="ts">
import { computed } from 'vue';

/**
 * Barra de alcance: qué proporción del catálogo de permisos cubre un rol.
 *
 * WHY: «11 de 39 permisos» obliga a hacer la división mentalmente para comparar
 * dos roles. La barra responde de un vistazo la pregunta real —¿este rol es
 * amplio o estrecho?— y el número sigue ahí para quien necesite la cifra.
 */
type Props = {
    value: number;
    total: number;
    label?: string;
};

const props = withDefaults(defineProps<Props>(), { label: undefined });

const percent = computed(() => {
    if (props.total <= 0) {
        return 0;
    }

    return Math.min(100, Math.round((props.value / props.total) * 100));
});
</script>

<template>
    <div
        class="h-1.5 w-full overflow-hidden rounded-full bg-brand-line-2"
        role="progressbar"
        :aria-valuenow="props.value"
        :aria-valuemin="0"
        :aria-valuemax="props.total"
        :aria-label="props.label"
    >
        <div
            class="h-full rounded-full bg-primary transition-[width]"
            :style="{ width: `${percent}%` }"
        />
    </div>
</template>
