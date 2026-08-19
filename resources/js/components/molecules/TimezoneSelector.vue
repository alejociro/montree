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

type TimezoneOption = {
    value: string;
    label: string;
};

const timezones: TimezoneOption[] = [
    { value: 'America/Bogota', label: t('Bogotá (GMT-5)') },
    { value: 'America/Mexico_City', label: t('Ciudad de México (GMT-6)') },
    {
        value: 'America/Argentina/Buenos_Aires',
        label: t('Buenos Aires (GMT-3)'),
    },
    { value: 'America/Lima', label: t('Lima (GMT-5)') },
    { value: 'America/Santiago', label: t('Santiago (GMT-4)') },
    { value: 'America/Sao_Paulo', label: t('São Paulo (GMT-3)') },
    { value: 'Europe/Madrid', label: t('Madrid (GMT+1)') },
    { value: 'UTC', label: t('UTC') },
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
                <SelectValue :placeholder="$t('Seleccionar zona horaria')" />
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
