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
            return 'bg-emerald-100 text-emerald-900 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-100';
        case 'suspended':
            return 'bg-red-100 text-red-900 border-red-200 dark:bg-red-900/30 dark:text-red-100';
        case 'pending':
        default:
            return 'bg-amber-100 text-amber-900 border-amber-200 dark:bg-amber-900/30 dark:text-amber-100';
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
    <Badge variant="outline" :class="statusClasses">{{ label }}</Badge>
</template>
