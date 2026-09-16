<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import ImageUploadField from '@/components/molecules/ImageUploadField.vue';

type BrandingAssetErrors = {
    logo?: string;
    favicon?: string;
    hero_image?: string;
};

type Props = {
    logoUrl: string | null;
    faviconUrl: string | null;
    heroImageUrl: string | null;
    errors?: BrandingAssetErrors;
};

defineProps<Props>();

const logo = defineModel<File | null>('logo', { required: true });
const favicon = defineModel<File | null>('favicon', { required: true });
const heroImage = defineModel<File | null>('heroImage', { required: true });
const removeLogo = defineModel<boolean>('removeLogo', { required: true });
const removeHeroImage = defineModel<boolean>('removeHeroImage', {
    required: true,
});
</script>

<template>
    <section class="space-y-6">
        <Heading
            variant="small"
            :title="$t('Imágenes de la marca')"
            :description="
                $t(
                    'El logo acompaña al panel y a la tienda; la imagen principal es el fondo de la portada.',
                )
            "
        />

        <div class="grid gap-6 md:grid-cols-3">
            <ImageUploadField
                id="logo"
                v-model="logo"
                v-model:removed="removeLogo"
                :label="$t('Logo')"
                accept="image/png,image/jpeg,image/svg+xml,image/webp"
                :hint="$t('PNG, JPG, SVG o WEBP. Máximo 2 MB.')"
                :current-url="logoUrl"
                removable
                :error="errors?.logo"
            />

            <ImageUploadField
                id="favicon"
                v-model="favicon"
                :label="$t('Favicon')"
                accept="image/png,image/x-icon,image/svg+xml"
                :hint="$t('PNG, ICO o SVG. Máximo 1 MB.')"
                :current-url="faviconUrl"
                preview-class="h-8 w-auto"
                :error="errors?.favicon"
            />

            <ImageUploadField
                id="hero_image"
                v-model="heroImage"
                v-model:removed="removeHeroImage"
                :label="$t('Imagen principal')"
                accept="image/jpeg,image/png,image/webp"
                :hint="$t('JPG, PNG o WEBP. Máximo 5 MB.')"
                :current-url="heroImageUrl"
                preview-class="h-20 w-full object-cover"
                removable
                :error="errors?.hero_image"
            />
        </div>
    </section>
</template>
