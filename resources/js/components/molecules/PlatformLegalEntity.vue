<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';

withDefaults(defineProps<{ inline?: boolean }>(), { inline: false });

const { t } = useTranslations();
const page = usePage();

// WHY: solo se listan los campos configurados (config/montree.php → legal).
// Un campo vacío no se inventa ni se rellena con un marcador visible.
const rows = computed(() => {
    const legal = page.props.platform?.legal;

    if (!legal) {
        return [];
    }

    return [
        { label: t('Responsable'), value: legal.name },
        { label: t('NIT'), value: legal.nit },
        { label: t('Domicilio'), value: legal.city },
        { label: t('Dirección'), value: legal.address },
        { label: t('Teléfono'), value: legal.phone },
        { label: t('Correo'), value: legal.privacy_email },
    ].filter((row): row is { label: string; value: string } =>
        Boolean(row.value),
    );
});

const inlineText = computed(() =>
    rows.value.map((row) => `${row.label}: ${row.value}`).join(' · '),
);
</script>

<template>
    <p v-if="inline && rows.length">
        {{ inlineText }}
    </p>
    <dl v-else-if="rows.length">
        <template v-for="row in rows" :key="row.label">
            <dt>{{ row.label }}</dt>
            <dd>{{ row.value }}</dd>
        </template>
    </dl>
</template>
