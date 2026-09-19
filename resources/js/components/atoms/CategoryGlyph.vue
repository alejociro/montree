<script setup lang="ts">
import { computed } from 'vue';
import { categoryIconComponent } from '@/lib/categories';
import { cn } from '@/lib/utils';

/**
 * Cara visible de una categoría: su imagen si la agencia subió una, y si no el
 * ícono Lucide que eligió. Vive en un átomo porque la regla "manda la imagen"
 * se pinta en el catálogo, el home, las fichas y el panel.
 */
type Props = {
    name: string;
    icon: string | null;
    imageUrl?: string | null;
    /** Clases del cuadro: tamaño y forma los decide quien lo usa. */
    class?: string;
    iconClass?: string;
};

const props = withDefaults(defineProps<Props>(), {
    imageUrl: null,
    class: undefined,
    iconClass: 'size-5',
});

const icon = computed(() => categoryIconComponent(props.icon));
</script>

<template>
    <img
        v-if="imageUrl"
        :src="imageUrl"
        :alt="name"
        :class="cn('object-cover', props.class)"
    />
    <component
        :is="icon"
        v-else
        :class="cn(iconClass, props.class)"
        aria-hidden="true"
    />
</template>
