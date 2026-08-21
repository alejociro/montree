<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { TourStatus } from '@/types/tour';

type Props = {
    status: TourStatus;
};

const props = defineProps<Props>();

/**
 * WHY: el estado del tour va por los tokens semánticos FIJOS (D5), no por
 * `--primary`: con el color del tenant, una agencia con el principal en rojo
 * mostraría «Activo» en rojo y «Pausado» sin contraste con él.
 */
const tones: Record<TourStatus, string> = {
    active: 'bg-brand-green-50 text-brand-green-600 border-brand-green-600/25',
    paused: 'bg-brand-warn-50 text-brand-warn border-brand-warn/30',
    archived: 'bg-muted text-muted-foreground border-brand-line-2',
    draft: 'bg-card text-muted-foreground border-border',
};

const labels: Record<TourStatus, string> = {
    active: 'Activo',
    paused: 'Pausado',
    archived: 'Archivado',
    draft: 'Borrador',
};

const tone = computed(() => tones[props.status]);
const label = computed(() => labels[props.status]);
</script>

<template>
    <Badge variant="outline" :class="tone">{{ $t(label) }}</Badge>
</template>
