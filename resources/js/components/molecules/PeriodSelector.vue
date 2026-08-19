<script setup lang="ts">
import type { AcceptableValue } from 'reka-ui';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/composables/useTranslations';
import type { DashboardPeriodKey } from '@/types/dashboard';

const { t } = useTranslations();

type Props = {
    modelValue: DashboardPeriodKey;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: DashboardPeriodKey): void;
}>();

type Option = {
    value: DashboardPeriodKey;
    label: string;
};

const options: Option[] = [
    { value: 'last_7_days', label: t('Últimos 7 días') },
    { value: 'last_30_days', label: t('Últimos 30 días') },
    { value: 'last_90_days', label: t('Últimos 90 días') },
    { value: 'this_month', label: t('Este mes') },
    { value: 'last_month', label: t('Mes pasado') },
    { value: 'this_year', label: t('Este año') },
];

function handleChange(value: AcceptableValue): void {
    if (typeof value !== 'string') {
        return;
    }

    emit('update:modelValue', value as DashboardPeriodKey);
}
</script>

<template>
    <Select :model-value="props.modelValue" @update:model-value="handleChange">
        <SelectTrigger class="w-[180px]">
            <SelectValue :placeholder="$t('Seleccionar periodo')" />
        </SelectTrigger>
        <SelectContent>
            <SelectGroup>
                <SelectItem
                    v-for="option in options"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </SelectItem>
            </SelectGroup>
        </SelectContent>
    </Select>
</template>
