<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type GatewayValues = {
    placetopay_login: string;
    placetopay_tran_key: string;
    placetopay_url: string;
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
                <Label for="placetopay_url">
                    {{ $t('URL del checkout') }}
                </Label>
                <Input
                    id="placetopay_url"
                    :model-value="modelValue.placetopay_url"
                    autocomplete="off"
                    placeholder="https://checkout.placetopay.com"
                    @update:model-value="
                        (v) => update('placetopay_url', String(v ?? ''))
                    "
                />
                <p class="text-xs text-muted-foreground">
                    {{
                        $t(
                            'El endpoint del país de tu comercio. Vacío usa el de la plataforma.',
                        )
                    }}
                </p>
                <InputError :message="errors?.placetopay_url" />
            </div>
        </div>
    </section>
</template>
