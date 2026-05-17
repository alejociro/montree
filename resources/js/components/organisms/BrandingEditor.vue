<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ColorPicker from '@/components/molecules/ColorPicker.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type BrandingValues = {
    primary_color: string;
    secondary_color: string;
    tagline: string;
    description: string;
};

type BrandingErrors = Partial<Record<keyof BrandingValues, string | undefined>>;

type Props = {
    modelValue: BrandingValues;
    errors?: BrandingErrors;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: BrandingValues): void;
}>();

function update<K extends keyof BrandingValues>(
    key: K,
    value: BrandingValues[K],
): void {
    emit('update:modelValue', { ...props.modelValue, [key]: value });
}

function onTagline(value: string | number): void {
    update('tagline', String(value));
}

function onDescription(value: string | number): void {
    update('description', String(value));
}
</script>

<template>
    <section class="space-y-6">
        <Heading
            variant="small"
            title="Identidad visual"
            description="Colores y mensajes que ven los viajeros en tu tienda."
        />

        <div class="grid gap-6 md:grid-cols-2">
            <ColorPicker
                id="primary_color"
                label="Color primario"
                description="Usado para botones de acción y enlaces destacados."
                :model-value="modelValue.primary_color"
                :error="errors?.primary_color"
                @update:model-value="(v) => update('primary_color', v)"
            />

            <ColorPicker
                id="secondary_color"
                label="Color secundario"
                description="Acentos, bordes y elementos de soporte."
                :model-value="modelValue.secondary_color"
                :error="errors?.secondary_color"
                @update:model-value="(v) => update('secondary_color', v)"
            />
        </div>

        <div class="grid gap-2">
            <Label for="tagline">Tagline</Label>
            <Input
                id="tagline"
                :model-value="modelValue.tagline"
                placeholder="Aventuras inolvidables en Colombia"
                maxlength="160"
                @update:model-value="onTagline"
            />
            <p class="text-xs text-muted-foreground">Máximo 160 caracteres.</p>
            <InputError :message="errors?.tagline" />
        </div>

        <div class="grid gap-2">
            <Label for="description">Descripción</Label>
            <Textarea
                id="description"
                :model-value="modelValue.description"
                placeholder="Contá brevemente qué hace única a tu agencia"
                rows="4"
                maxlength="2000"
                @update:model-value="onDescription"
            />
            <p class="text-xs text-muted-foreground">Máximo 2000 caracteres.</p>
            <InputError :message="errors?.description" />
        </div>
    </section>
</template>
