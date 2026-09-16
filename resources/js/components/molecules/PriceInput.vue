<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTenantCurrency } from '@/composables/useTenant';

type Props = {
    id: string;
    label: string;
    modelValue: string;
    priceError?: string;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const currency = useTenantCurrency();

function handlePrice(value: string | number): void {
    emit('update:modelValue', String(value));
}
</script>

<template>
    <div class="grid content-start gap-2">
        <Label :for="id">{{ label }}</Label>
        <div class="flex gap-2">
            <Input
                :id="id"
                type="number"
                step="0.01"
                min="0"
                :model-value="props.modelValue"
                class="flex-1"
                @update:model-value="handlePrice"
            />
            <span
                class="flex w-20 items-center justify-center rounded-md border border-input bg-muted text-sm font-medium text-muted-foreground"
                :title="$t('La moneda es la de la agencia y se cambia en su configuración.')"
            >
                {{ currency }}
            </span>
        </div>
        <InputError :message="priceError" />
    </div>
</template>
