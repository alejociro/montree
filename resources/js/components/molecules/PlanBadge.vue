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
            return 'border-brand-ink bg-brand-ink text-brand-cream';
        case 'professional':
            return 'bg-secondary text-primary border-secondary';
        case 'basic':
        default:
            return 'bg-muted text-foreground border-border';
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
