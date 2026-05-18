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
import type { DashboardPeriodKey } from '@/types/dashboard';

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
    { value: 'last_7_days', label: 'Últimos 7 días' },
    { value: 'last_30_days', label: 'Últimos 30 días' },
    { value: 'last_90_days', label: 'Últimos 90 días' },
    { value: 'this_month', label: 'Este mes' },
    { value: 'last_month', label: 'Mes pasado' },
    { value: 'this_year', label: 'Este año' },
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
            <SelectValue placeholder="Seleccionar periodo" />
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
