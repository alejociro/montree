<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import LocaleSwitcher from '@/components/molecules/LocaleSwitcher.vue';
import { useTranslations } from '@/composables/useTranslations';
import { edit } from '@/routes/appearance';

const { t } = useTranslations();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Apariencia',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="$t('Apariencia')" />

    <h1 class="sr-only">{{ $t('Apariencia') }}</h1>

    <div class="space-y-8">
        <div class="space-y-6">
            <Heading
                variant="small"
                :title="t('Apariencia')"
                :description="
                    t('Elegí cómo se ve la aplicación en este dispositivo')
                "
            />
            <AppearanceTabs />
        </div>

        <div class="space-y-6">
            <Heading
                variant="small"
                :title="t('Idioma')"
                :description="
                    t(
                        'El idioma se guarda en tu cuenta y te sigue a cualquier dispositivo',
                    )
                "
            />
            <LocaleSwitcher variant="tabs" />
        </div>
    </div>
</template>
