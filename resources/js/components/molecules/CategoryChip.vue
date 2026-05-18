<script setup lang="ts">
import { computed } from 'vue';

type Props = {
    label: string;
    count?: number | null;
    selected?: boolean;
    icon?: string | null;
};

const props = withDefaults(defineProps<Props>(), {
    count: null,
    selected: false,
    icon: null,
});

defineEmits<{
    (event: 'select'): void;
}>();

const classes = computed(() => [
    'inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/60',
    props.selected
        ? 'border-primary bg-primary text-primary-foreground shadow-sm'
        : 'border-border bg-card text-foreground hover:bg-accent hover:text-accent-foreground',
]);
</script>

<template>
    <button type="button" :class="classes" @click="$emit('select')">
        <span v-if="icon" aria-hidden="true">·</span>
        <span>{{ label }}</span>
        <span
            v-if="count !== null"
            class="rounded-full bg-background/30 px-1.5 text-[10px] leading-4 font-semibold text-current"
            :class="selected ? 'bg-primary-foreground/20' : 'bg-muted'"
        >
            {{ count }}
        </span>
    </button>
</template>
