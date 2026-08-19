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
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

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
    { code: 'USD', label: t('USD — US Dollar') },
    { code: 'COP', label: t('COP — Peso Colombiano') },
    { code: 'EUR', label: t('EUR — Euro') },
    { code: 'MXN', label: t('MXN — Peso Mexicano') },
    { code: 'ARS', label: t('ARS — Peso Argentino') },
    { code: 'PEN', label: t('PEN — Sol Peruano') },
    { code: 'CLP', label: t('CLP — Peso Chileno') },
    { code: 'BRL', label: t('BRL — Real Brasileño') },
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
                <SelectValue :placeholder="$t('Seleccionar moneda')" />
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
