<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { TourStatus } from '@/types/tour';

type Props = {
    status: TourStatus;
};

const props = defineProps<Props>();

const variant = computed(() => {
    switch (props.status) {
        case 'active':
            return 'default';
        case 'paused':
            return 'secondary';
        case 'archived':
            return 'destructive';
        case 'draft':
        default:
            return 'outline';
    }
});

const label = computed(() => {
    switch (props.status) {
        case 'active':
            return 'Activo';
        case 'paused':
            return 'Pausado';
        case 'archived':
            return 'Archivado';
        case 'draft':
        default:
            return 'Borrador';
    }
});
</script>

<template>
    <Badge :variant="variant">{{ label }}</Badge>
</template>
