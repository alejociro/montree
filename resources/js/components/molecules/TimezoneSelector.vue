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

type TimezoneOption = {
    value: string;
    label: string;
};

const timezones: TimezoneOption[] = [
    { value: 'America/Bogota', label: 'Bogotá (GMT-5)' },
    { value: 'America/Mexico_City', label: 'Ciudad de México (GMT-6)' },
    {
        value: 'America/Argentina/Buenos_Aires',
        label: 'Buenos Aires (GMT-3)',
    },
    { value: 'America/Lima', label: 'Lima (GMT-5)' },
    { value: 'America/Santiago', label: 'Santiago (GMT-4)' },
    { value: 'America/Sao_Paulo', label: 'São Paulo (GMT-3)' },
    { value: 'Europe/Madrid', label: 'Madrid (GMT+1)' },
    { value: 'UTC', label: 'UTC' },
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
                <SelectValue placeholder="Seleccionar zona horaria" />
            </SelectTrigger>
            <SelectContent>
                <SelectGroup>
                    <SelectItem
                        v-for="tz in timezones"
                        :key="tz.value"
                        :value="tz.value"
                    >
                        {{ tz.label }}
                    </SelectItem>
                </SelectGroup>
            </SelectContent>
        </Select>
        <InputError :message="error" />
    </div>
</template>
