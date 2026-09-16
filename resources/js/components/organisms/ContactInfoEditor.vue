<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { TenantContactInfo } from '@/types/tenant';

type ContactField = 'address' | 'email' | 'phone' | 'whatsapp';

type Props = {
    modelValue: TenantContactInfo;
    errors?: Partial<Record<ContactField, string | undefined>>;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: TenantContactInfo): void;
}>();

function update(key: ContactField, value: string | number): void {
    emit('update:modelValue', { ...props.modelValue, [key]: String(value) });
}
</script>

<template>
    <section class="space-y-6">
        <Heading
            variant="small"
            :title="$t('Información de contacto')"
            :description="$t('Se muestra en el pie de página de tu tienda.')"
        />

        <div class="grid gap-6 md:grid-cols-2">
            <div class="grid content-start gap-2">
                <Label for="contact_address">{{ $t('Dirección') }}</Label>
                <Input
                    id="contact_address"
                    :model-value="modelValue.address ?? ''"
                    placeholder="Calle 10 #4-20, Salento"
                    maxlength="255"
                    @update:model-value="(v) => update('address', v)"
                />
                <InputError :message="errors?.address" />
            </div>

            <div class="grid content-start gap-2">
                <Label for="contact_email">{{ $t('Email de contacto') }}</Label>
                <Input
                    id="contact_email"
                    type="email"
                    :model-value="modelValue.email ?? ''"
                    placeholder="hola@agencia.co"
                    maxlength="255"
                    @update:model-value="(v) => update('email', v)"
                />
                <InputError :message="errors?.email" />
            </div>

            <div class="grid content-start gap-2">
                <Label for="contact_phone">{{ $t('Teléfono') }}</Label>
                <Input
                    id="contact_phone"
                    :model-value="modelValue.phone ?? ''"
                    placeholder="+57 300 000 0000"
                    maxlength="40"
                    @update:model-value="(v) => update('phone', v)"
                />
                <InputError :message="errors?.phone" />
            </div>

            <div class="grid content-start gap-2">
                <Label for="contact_whatsapp">{{ $t('WhatsApp') }}</Label>
                <Input
                    id="contact_whatsapp"
                    :model-value="modelValue.whatsapp ?? ''"
                    placeholder="+57 300 000 0000"
                    maxlength="40"
                    @update:model-value="(v) => update('whatsapp', v)"
                />
                <InputError :message="errors?.whatsapp" />
            </div>
        </div>
    </section>
</template>
