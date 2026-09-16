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
import { CURRENCY_VALUES } from '@/types/enums.generated';
import type { Currency } from '@/types/enums.generated';

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

const CURRENCY_LABELS: Record<Currency, string> = {
    USD: t('USD — US Dollar'),
    COP: t('COP — Peso Colombiano'),
    EUR: t('EUR — Euro'),
    MXN: t('MXN — Peso Mexicano'),
    ARS: t('ARS — Peso Argentino'),
    PEN: t('PEN — Sol Peruano'),
    CLP: t('CLP — Peso Chileno'),
    BRL: t('BRL — Real Brasileño'),
};

const currencies: CurrencyOption[] = CURRENCY_VALUES.map((code) => ({
    code,
    label: CURRENCY_LABELS[code],
}));

function handleChange(value: AcceptableValue): void {
    if (typeof value !== 'string') {
        return;
    }

    emit('update:modelValue', value);
}
</script>

<template>
    <div class="grid content-start gap-2">
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
