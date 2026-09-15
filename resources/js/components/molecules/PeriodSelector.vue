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
import type {
    DashboardPeriodKey,
    DashboardPeriodOption,
} from '@/types/dashboard';

/**
 * WHY las opciones llegan por prop: antes se duplicaban acá espejando
 * `PeriodFilter::SUPPORTED_KEYS`, y cada periodo nuevo del backend quedaba
 * invisible hasta que alguien se acordara de tocar este archivo.
 */
type Props = {
    modelValue: DashboardPeriodKey;
    periods: DashboardPeriodOption[];
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: DashboardPeriodKey): void;
}>();

function handleChange(value: AcceptableValue): void {
    if (typeof value !== 'string') {
        return;
    }

    emit('update:modelValue', value as DashboardPeriodKey);
}
</script>

<template>
    <Select :model-value="props.modelValue" @update:model-value="handleChange">
        <SelectTrigger
            class="w-[180px]"
            :aria-label="$t('Seleccionar periodo')"
        >
            <SelectValue :placeholder="$t('Seleccionar periodo')" />
        </SelectTrigger>
        <SelectContent>
            <SelectGroup>
                <SelectItem
                    v-for="option in props.periods"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </SelectItem>
            </SelectGroup>
        </SelectContent>
    </Select>
</template>
