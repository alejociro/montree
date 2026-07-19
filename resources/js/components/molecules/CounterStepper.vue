<script setup lang="ts">
import { Minus, Plus } from 'lucide-vue-next';

type Props = {
    label: string;
    modelValue: number;
    description?: string;
    min?: number;
    max?: number;
};

const props = withDefaults(defineProps<Props>(), {
    description: undefined,
    min: 0,
    max: Number.POSITIVE_INFINITY,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: number): void;
}>();

function decrement(): void {
    if (props.modelValue > props.min) {
        emit('update:modelValue', props.modelValue - 1);
    }
}

function increment(): void {
    if (props.modelValue < props.max) {
        emit('update:modelValue', props.modelValue + 1);
    }
}
</script>

<template>
    <div
        class="flex items-center justify-between rounded-lg border border-border bg-card p-4"
    >
        <div>
            <p class="text-sm font-medium text-foreground">{{ label }}</p>
            <p v-if="description" class="mt-0.5 text-xs text-muted-foreground">
                {{ description }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            <button
                type="button"
                class="flex size-8 items-center justify-center rounded-full border border-border text-foreground transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="modelValue <= min"
                :aria-label="`Disminuir ${label}`"
                @click="decrement"
            >
                <Minus class="size-4" />
            </button>
            <span
                class="w-6 text-center text-base font-semibold text-foreground"
                aria-live="polite"
            >
                {{ modelValue }}
            </span>
            <button
                type="button"
                class="flex size-8 items-center justify-center rounded-full border border-border text-foreground transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="modelValue >= max"
                :aria-label="`Aumentar ${label}`"
                @click="increment"
            >
                <Plus class="size-4" />
            </button>
        </div>
    </div>
</template>
