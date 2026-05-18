<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { TenantPlan } from '@/types';

const props = defineProps<{
    plan: TenantPlan;
}>();

const planClasses = computed(() => {
    switch (props.plan) {
        case 'enterprise':
            return 'bg-indigo-100 text-indigo-900 border-indigo-200 dark:bg-indigo-900/30 dark:text-indigo-100';
        case 'professional':
            return 'bg-emerald-100 text-emerald-900 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-100';
        case 'basic':
        default:
            return 'bg-zinc-100 text-zinc-900 border-zinc-200 dark:bg-zinc-800 dark:text-zinc-100';
    }
});

const label = computed(() => {
    switch (props.plan) {
        case 'enterprise':
            return 'Enterprise';
        case 'professional':
            return 'Professional';
        case 'basic':
            return 'Basic';
        default:
            return props.plan;
    }
});
</script>

<template>
    <Badge variant="outline" :class="planClasses">{{ label }}</Badge>
</template>
