<script setup lang="ts">
import type { AcceptableValue } from 'reka-ui';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Props = {
    id: string;
    label: string;
    modelValue: string | null;
    error?: string;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

type CurrencyOption = {
    code: string;
    label: string;
};

const currencies: CurrencyOption[] = [
    { code: 'USD', label: 'USD — US Dollar' },
    { code: 'COP', label: 'COP — Peso Colombiano' },
    { code: 'EUR', label: 'EUR — Euro' },
    { code: 'MXN', label: 'MXN — Peso Mexicano' },
    { code: 'ARS', label: 'ARS — Peso Argentino' },
    { code: 'PEN', label: 'PEN — Sol Peruano' },
    { code: 'CLP', label: 'CLP — Peso Chileno' },
    { code: 'BRL', label: 'BRL — Real Brasileño' },
];

function handleChange(value: AcceptableValue): void {
    if (typeof value !== 'string') {
        return;
    }

    emit('update:modelValue', value);
}
</script>

<template>
    <div class="grid gap-2">
        <Label :for="id">{{ label }}</Label>
        <Select
            :model-value="props.modelValue ?? undefined"
            @update:model-value="handleChange"
        >
            <SelectTrigger :id="id" class="w-full">
                <SelectValue placeholder="Seleccionar moneda" />
            </SelectTrigger>
            <SelectContent>
                <SelectGroup>
                    <SelectItem
                        v-for="currency in currencies"
                        :key="currency.code"
                        :value="currency.code"
                    >
                        {{ currency.label }}
                    </SelectItem>
                </SelectGroup>
            </SelectContent>
        </Select>
        <InputError :message="error" />
    </div>
</template>
