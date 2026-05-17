<script setup lang="ts">
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    id: string;
    label: string;
    modelValue: string;
    error?: string;
    description?: string;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const HEX_REGEX = /^#[0-9A-Fa-f]{6}$/;

const isValidHex = computed(() => HEX_REGEX.test(props.modelValue ?? ''));

const safeColor = computed(() =>
    isValidHex.value ? props.modelValue : '#000000',
);

function onColorChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    emit('update:modelValue', target.value);
}

function onHexChange(value: string | number): void {
    emit('update:modelValue', String(value));
}
</script>

<template>
    <div class="grid gap-2">
        <Label :for="id">{{ label }}</Label>

        <div class="flex items-center gap-3">
            <label
                :for="`${id}-picker`"
                class="relative inline-flex h-10 w-10 cursor-pointer items-center justify-center overflow-hidden rounded-md border border-input shadow-xs"
                :aria-label="`Pick ${label}`"
            >
                <span
                    class="absolute inset-0"
                    :style="{ backgroundColor: safeColor }"
                />
                <input
                    :id="`${id}-picker`"
                    type="color"
                    class="absolute inset-0 cursor-pointer opacity-0"
                    :value="safeColor"
                    @input="onColorChange"
                />
            </label>

            <Input
                :id="id"
                :model-value="modelValue"
                placeholder="#16a34a"
                class="font-mono uppercase"
                :maxlength="7"
                :aria-invalid="!isValidHex && !!modelValue"
                @update:model-value="onHexChange"
            />
        </div>

        <p v-if="description" class="text-xs text-muted-foreground">
            {{ description }}
        </p>

        <p
            v-if="modelValue && !isValidHex"
            class="text-xs text-amber-600 dark:text-amber-400"
        >
            Formato esperado: #RRGGBB (6 dígitos hexadecimales).
        </p>

        <InputError :message="error" />
    </div>
</template>
