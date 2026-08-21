<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { TourDateStatus } from '@/types/logistics';

type Props = {
    status: TourDateStatus;
};

const props = defineProps<Props>();

/**
 * WHY: el estado de una salida va por los tokens semánticos FIJOS (D5), igual
 * que el del tour. Con las variantes de `Badge`, «Abierta» se pintaba con
 * `--primary` y una agencia con el principal en rojo anunciaba en rojo que la
 * salida está a la venta.
 */
const tones: Record<TourDateStatus, string> = {
    open: 'bg-brand-green-50 text-brand-green-600 border-brand-green-600/25',
    full: 'bg-muted text-foreground border-brand-line-2',
    closed: 'bg-card text-muted-foreground border-border',
    cancelled: 'bg-destructive/10 text-destructive border-destructive/25',
};

const labels: Record<TourDateStatus, string> = {
    open: 'Abierta',
    full: 'Completa',
    closed: 'Cerrada',
    cancelled: 'Cancelada',
};

const tone = computed(() => tones[props.status]);
const label = computed(() => labels[props.status]);
</script>

<template>
    <Badge variant="outline" :class="tone">{{ $t(label) }}</Badge>
</template>
