<script setup lang="ts">
import { Facebook, Instagram, Music2, Twitter, Youtube } from 'lucide-vue-next';
import type { Component } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { TenantSocialLinks } from '@/types/tenant';

type Props = {
    modelValue: TenantSocialLinks;
    errors?: Partial<Record<keyof TenantSocialLinks, string | undefined>>;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: TenantSocialLinks): void;
}>();

type SocialField = {
    key: keyof TenantSocialLinks;
    label: string;
    icon: Component;
    placeholder: string;
};

const fields: SocialField[] = [
    {
        key: 'instagram',
        label: 'Instagram',
        icon: Instagram,
        placeholder: 'https://instagram.com/tu-agencia',
    },
    {
        key: 'facebook',
        label: 'Facebook',
        icon: Facebook,
        placeholder: 'https://facebook.com/tu-agencia',
    },
    {
        key: 'twitter',
        label: 'Twitter / X',
        icon: Twitter,
        placeholder: 'https://twitter.com/tu-agencia',
    },
    {
        key: 'youtube',
        label: 'YouTube',
        icon: Youtube,
        placeholder: 'https://youtube.com/@tu-agencia',
    },
    {
        key: 'tiktok',
        label: 'TikTok',
        icon: Music2,
        placeholder: 'https://tiktok.com/@tu-agencia',
    },
];

function update(key: keyof TenantSocialLinks, value: string | number): void {
    const next = { ...props.modelValue, [key]: String(value) };

    if (!next[key]) {
        delete next[key];
    }

    emit('update:modelValue', next);
}
</script>

<template>
    <section class="space-y-6">
        <Heading
            variant="small"
            title="Redes sociales"
            description="Enlaces opcionales que aparecen en el footer del sitio."
        />

        <div class="grid gap-4">
            <div v-for="field in fields" :key="field.key" class="grid gap-2">
                <Label
                    :for="`social-${field.key}`"
                    class="flex items-center gap-2"
                >
                    <component
                        :is="field.icon"
                        class="size-4 text-muted-foreground"
                    />
                    {{ field.label }}
                </Label>
                <Input
                    :id="`social-${field.key}`"
                    type="url"
                    :model-value="modelValue[field.key] ?? ''"
                    :placeholder="field.placeholder"
                    @update:model-value="(v) => update(field.key, v)"
                />
                <InputError :message="errors?.[field.key]" />
            </div>
        </div>
    </section>
</template>
