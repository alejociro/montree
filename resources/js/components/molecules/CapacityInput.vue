<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    id: string;
    label: string;
    modelValue: number;
    description?: string;
    error?: string;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: number): void;
}>();

function handleChange(value: string | number): void {
    const parsed =
        typeof value === 'number' ? value : parseInt(value || '0', 10);
    emit('update:modelValue', Number.isNaN(parsed) ? 0 : parsed);
}
</script>

<template>
    <div class="grid gap-2">
        <Label :for="id">{{ label }}</Label>
        <Input
            :id="id"
            type="number"
            min="1"
            max="500"
            :model-value="props.modelValue"
            @update:model-value="handleChange"
        />
        <p v-if="description" class="text-xs text-muted-foreground">
            {{ description }}
        </p>
        <InputError :message="error" />
    </div>
</template>
