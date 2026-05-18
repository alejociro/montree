<script setup lang="ts">
import type { AcceptableValue } from 'reka-ui';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { SUPPORTED_CURRENCIES } from '@/types/tour';
import type { SupportedCurrency } from '@/types/tour';

type Props = {
    id: string;
    label: string;
    modelValue: string;
    currency: SupportedCurrency;
    priceError?: string;
    currencyError?: string;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
    (e: 'update:currency', value: SupportedCurrency): void;
}>();

function handlePrice(value: string | number): void {
    emit('update:modelValue', String(value));
}

function handleCurrency(value: AcceptableValue): void {
    if (typeof value !== 'string') {
        return;
    }

    emit('update:currency', value as SupportedCurrency);
}
</script>

<template>
    <div class="grid gap-2">
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
            <Select
                :model-value="props.currency"
                @update:model-value="handleCurrency"
            >
                <SelectTrigger class="w-32">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        <SelectItem
                            v-for="code in SUPPORTED_CURRENCIES"
                            :key="code"
                            :value="code"
                        >
                            {{ code }}
                        </SelectItem>
                    </SelectGroup>
                </SelectContent>
            </Select>
        </div>
        <InputError :message="priceError" />
        <InputError :message="currencyError" />
    </div>
</template>
