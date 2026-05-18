<script setup lang="ts">
import { ArrowDownRight, ArrowUpRight, Minus } from 'lucide-vue-next';
import { computed } from 'vue';
import { formatPercent } from '@/lib/format';

type Props = {
    value: number | null;
    label?: string;
};

const props = defineProps<Props>();

const direction = computed<'up' | 'down' | 'flat' | 'unknown'>(() => {
    if (props.value === null) {
        return 'unknown';
    }

    if (props.value > 0) {
        return 'up';
    }

    if (props.value < 0) {
        return 'down';
    }

    return 'flat';
});

const colorClass = computed(() => {
    switch (direction.value) {
        case 'up':
            return 'text-emerald-600 dark:text-emerald-400';
        case 'down':
            return 'text-rose-600 dark:text-rose-400';
        default:
            return 'text-muted-foreground';
    }
});

const Icon = computed(() => {
    switch (direction.value) {
        case 'up':
            return ArrowUpRight;
        case 'down':
            return ArrowDownRight;
        default:
            return Minus;
    }
});
</script>

<template>
    <span
        class="inline-flex items-center gap-1 text-xs font-medium"
        :class="colorClass"
    >
        <component :is="Icon" class="size-3.5" />
        <span>{{ formatPercent(props.value) }}</span>
        <span v-if="label" class="text-muted-foreground">{{ label }}</span>
    </span>
</template>
