<script setup lang="ts">
import type { AcceptableValue } from 'reka-ui';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import CurrencySelector from '@/components/molecules/CurrencySelector.vue';
import TimezoneSelector from '@/components/molecules/TimezoneSelector.vue';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import type { TenantLocale } from '@/types/tenant';

type OperationalValues = {
    currency: string;
    timezone: string;
    locale: TenantLocale;
    reviews_require_moderation: boolean;
    require_traveler_details: boolean;
};

type OperationalErrors = Partial<
    Record<keyof OperationalValues, string | undefined>
>;

type Props = {
    modelValue: OperationalValues;
    errors?: OperationalErrors;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: OperationalValues): void;
}>();

function update<K extends keyof OperationalValues>(
    key: K,
    value: OperationalValues[K],
): void {
    emit('update:modelValue', { ...props.modelValue, [key]: value });
}

function onLocaleChange(value: AcceptableValue): void {
    if (value !== 'es' && value !== 'en') {
        return;
    }

    update('locale', value);
}
</script>

<template>
    <section class="space-y-6">
        <Heading
            variant="small"
            title="Configuración operativa"
            description="Cómo opera tu agencia: moneda, idioma, zona horaria y reglas."
        />

        <div class="grid gap-6 md:grid-cols-2">
            <CurrencySelector
                id="currency"
                label="Moneda"
                :model-value="modelValue.currency"
                :error="errors?.currency"
                @update:model-value="(v) => update('currency', v)"
            />

            <TimezoneSelector
                id="timezone"
                label="Zona horaria"
                :model-value="modelValue.timezone"
                :error="errors?.timezone"
                @update:model-value="(v) => update('timezone', v)"
            />

            <div class="grid gap-2">
                <Label for="locale">Idioma</Label>
                <Select
                    :model-value="modelValue.locale"
                    @update:model-value="onLocaleChange"
                >
                    <SelectTrigger id="locale" class="w-full">
                        <SelectValue placeholder="Seleccionar idioma" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem value="es">Español</SelectItem>
                            <SelectItem value="en">English</SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>
                <InputError :message="errors?.locale" />
            </div>
        </div>

        <div class="space-y-4 rounded-md border border-input bg-muted/30 p-4">
            <div class="flex items-start justify-between gap-4">
                <div class="space-y-0.5">
                    <Label
                        for="reviews_require_moderation"
                        class="text-sm font-medium"
                    >
                        Moderar reseñas antes de publicar
                    </Label>
                    <p class="text-xs text-muted-foreground">
                        Las reseñas quedan en revisión hasta que las aprobás.
                    </p>
                </div>
                <Switch
                    id="reviews_require_moderation"
                    :model-value="modelValue.reviews_require_moderation"
                    @update:model-value="
                        (v) => update('reviews_require_moderation', v)
                    "
                />
            </div>

            <div class="flex items-start justify-between gap-4">
                <div class="space-y-0.5">
                    <Label
                        for="require_traveler_details"
                        class="text-sm font-medium"
                    >
                        Requerir datos de cada viajero
                    </Label>
                    <p class="text-xs text-muted-foreground">
                        Solicita nombre, documento y contacto por persona al
                        reservar.
                    </p>
                </div>
                <Switch
                    id="require_traveler_details"
                    :model-value="modelValue.require_traveler_details"
                    @update:model-value="
                        (v) => update('require_traveler_details', v)
                    "
                />
            </div>
        </div>
    </section>
</template>
