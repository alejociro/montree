<script setup lang="ts">
import { computed } from 'vue';
import { getInitials } from '@/composables/useInitials';
import { cn } from '@/lib/utils';

type Props = {
    name: string | null;
    size?: 'sm' | 'md';
    /**
     * Fila de marcador de posición: la reserva existe, la persona todavía no.
     * El avatar lo dice en gris en vez de inventar unas iniciales.
     */
    pending?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    size: 'md',
    pending: false,
});

const initials = computed(() => getInitials(props.name ?? undefined) || '·');

const classes = computed(() =>
    cn(
        'inline-flex shrink-0 items-center justify-center rounded-full font-semibold uppercase select-none',
        props.size === 'sm' ? 'size-7 text-[10px]' : 'size-9 text-xs',
        props.pending
            ? 'bg-muted text-muted-foreground'
            : 'bg-primary/10 text-primary',
    ),
);
</script>

<template>
    <span :class="classes" aria-hidden="true">{{ initials }}</span>
</template>
