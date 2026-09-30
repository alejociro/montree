<script setup lang="ts">
import type { AcceptableValue } from 'reka-ui';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
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
import { useTranslations } from '@/composables/useTranslations';
import type { PlaceToPayEnvironment } from '@/types/enums.generated';

const { t } = useTranslations();

type GatewayValues = {
    placetopay_login: string;
    placetopay_tran_key: string;
    placetopay_environment: PlaceToPayEnvironment;
};

type GatewayErrors = Partial<Record<keyof GatewayValues, string | undefined>>;

type Props = {
    modelValue: GatewayValues;
    /** Ya hay un tranKey cifrado guardado: el campo pide reemplazo, no alta. */
    tranKeySet: boolean;
    errors?: GatewayErrors;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: GatewayValues): void;
}>();

function update<K extends keyof GatewayValues>(
    key: K,
    value: GatewayValues[K],
): void {
    emit('update:modelValue', { ...props.modelValue, [key]: value });
}

const environmentOptions: { value: PlaceToPayEnvironment; label: string }[] = [
    { value: 'test', label: t('Pruebas (sandbox)') },
    { value: 'production', label: t('Producción (cobros reales)') },
];

const isProduction = computed(
    () => props.modelValue.placetopay_environment === 'production',
);

function onEnvironmentChange(value: AcceptableValue): void {
    if (value !== 'test' && value !== 'production') {
        return;
    }

    update('placetopay_environment', value);
}
</script>

<template>
    <section class="space-y-6">
        <Heading
            variant="small"
            :title="$t('Pasarela de pagos')"
            :description="
                $t(
                    'Credenciales de tu comercio en PlacetoPay. Si las dejás vacías, los pagos se procesan con el comercio de la plataforma.',
                )
            "
        />

        <div class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
                <Label for="placetopay_login">{{ $t('Login') }}</Label>
                <Input
                    id="placetopay_login"
                    :model-value="modelValue.placetopay_login"
                    autocomplete="off"
                    :placeholder="$t('Sin configurar')"
                    @update:model-value="
                        (v) => update('placetopay_login', String(v ?? ''))
                    "
                />
                <InputError :message="errors?.placetopay_login" />
            </div>

            <div class="space-y-2">
                <Label for="placetopay_tran_key">{{ $t('TranKey') }}</Label>
                <Input
                    id="placetopay_tran_key"
                    type="password"
                    :model-value="modelValue.placetopay_tran_key"
                    autocomplete="new-password"
                    :placeholder="
                        tranKeySet
                            ? $t(
                                  'Guardado — escribí uno nuevo para reemplazarlo',
                              )
                            : $t('Sin configurar')
                    "
                    @update:model-value="
                        (v) => update('placetopay_tran_key', String(v ?? ''))
                    "
                />
                <InputError :message="errors?.placetopay_tran_key" />
            </div>

            <div class="space-y-2 md:col-span-2">
                <div class="flex items-center gap-2">
                    <Label for="placetopay_environment">
                        {{ $t('Ambiente') }}
                    </Label>
                    <Badge
                        v-if="isProduction"
                        variant="outline"
                        class="border-destructive/30 bg-destructive/10 text-destructive"
                    >
                        {{ $t('Cobros reales activos') }}
                    </Badge>
                </div>
                <Select
                    :model-value="modelValue.placetopay_environment"
                    @update:model-value="onEnvironmentChange"
                >
                    <SelectTrigger id="placetopay_environment" class="w-full">
                        <SelectValue
                            :placeholder="$t('Seleccionar ambiente')"
                        />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem
                                v-for="option in environmentOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>
                <p class="text-xs text-muted-foreground">
                    {{
                        $t(
                            'En Pruebas no se cobra dinero real; úsalo con las credenciales de pruebas de PlacetoPay. Cambia a Producción cuando PlacetoPay te entregue las credenciales productivas.',
                        )
                    }}
                </p>
                <InputError :message="errors?.placetopay_environment" />
            </div>
        </div>
    </section>
</template>
