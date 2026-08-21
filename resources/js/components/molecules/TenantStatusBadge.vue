<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { TenantStatus } from '@/types';

const props = defineProps<{
    status: TenantStatus;
}>();

const statusClasses = computed(() => {
    switch (props.status) {
        case 'active':
            return 'bg-secondary text-primary border-secondary';
        case 'suspended':
            return 'bg-destructive/10 text-destructive border-destructive/30';
        case 'pending':
        default:
            return 'bg-accent text-accent-foreground border-accent-foreground/25';
    }
});

const label = computed(() => {
    switch (props.status) {
        case 'active':
            return 'Activo';
        case 'suspended':
            return 'Suspendido';
        case 'pending':
            return 'Pendiente';
        default:
            return props.status;
    }
});
</script>

<template>
    <Badge variant="outline" :class="statusClasses">{{ $t(label) }}</Badge>
</template>
