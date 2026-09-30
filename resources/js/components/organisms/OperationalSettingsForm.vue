<script setup lang="ts">
import type { AcceptableValue } from 'reka-ui';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import CurrencySelector from '@/components/molecules/CurrencySelector.vue';
import TimezoneSelector from '@/components/molecules/TimezoneSelector.vue';
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
import { Switch } from '@/components/ui/switch';
import type { TenantLocale } from '@/types/tenant';

type OperationalValues = {
    currency: string;
    timezone: string;
    locale: TenantLocale;
    reviews_require_moderation: boolean;
    require_traveler_details: boolean;
    /** T12: horas antes del inicio de cada salida en que cierran las reservas. Vacío = sin regla. */
    booking_advance_hours: number | '';
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
            :title="$t('Configuración operativa')"
            :description="
                $t(
                    'Cómo opera tu agencia: moneda, idioma, zona horaria y reglas.',
                )
            "
        />

        <div class="grid gap-6 md:grid-cols-2">
            <CurrencySelector
                id="currency"
                :label="$t('Moneda')"
                :model-value="modelValue.currency"
                :error="errors?.currency"
                @update:model-value="(v) => update('currency', v)"
            />

            <TimezoneSelector
                id="timezone"
                :label="$t('Zona horaria')"
                :model-value="modelValue.timezone"
                :error="errors?.timezone"
                @update:model-value="(v) => update('timezone', v)"
            />

            <div class="grid content-start gap-2">
                <Label for="locale">{{ $t('Idioma') }}</Label>
                <Select
                    :model-value="modelValue.locale"
                    @update:model-value="onLocaleChange"
                >
                    <SelectTrigger id="locale" class="w-full">
                        <SelectValue :placeholder="$t('Seleccionar idioma')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem value="es">{{
                                $t('Español')
                            }}</SelectItem>
                            <SelectItem value="en">{{
                                $t('English')
                            }}</SelectItem>
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
                        {{ $t('Moderar reseñas antes de publicar') }}
                    </Label>
                    <p class="text-xs text-muted-foreground">
                        {{
                            $t(
                                'Las reseñas quedan en revisión hasta que las aprobás.',
                            )
                        }}
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
                        {{ $t('Requerir datos de cada viajero') }}
                    </Label>
                    <p class="text-xs text-muted-foreground">
                        {{
                            $t(
                                'Solicita nombre, documento y contacto por persona al reservar.',
                            )
                        }}
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

        <div class="grid gap-2 md:max-w-xs">
            <Label for="booking_advance_hours">
                {{ $t('Cierre de reservas por defecto (horas)') }}
            </Label>
            <Input
                id="booking_advance_hours"
                type="number"
                min="0"
                max="720"
                step="1"
                :model-value="modelValue.booking_advance_hours"
                :aria-invalid="Boolean(errors?.booking_advance_hours)"
                :placeholder="$t('Vacío: hasta la hora de salida')"
                @update:model-value="
                    (v) =>
                        update(
                            'booking_advance_hours',
                            v === '' || v === null ? '' : Number(v),
                        )
                "
            />
            <p class="text-xs text-muted-foreground">
                {{
                    $t(
                        'Las reservas de cada salida se cierran estas horas antes del inicio. Vacío: se puede reservar hasta la hora de salida. Cada salida puede tener su propio cierre.',
                    )
                }}
            </p>
            <InputError :message="errors?.booking_advance_hours" />
        </div>
    </section>
</template>
