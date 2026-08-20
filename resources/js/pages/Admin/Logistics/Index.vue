<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import LogisticsCrudPanel from '@/components/organisms/LogisticsCrudPanel.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { LogisticsField, LogisticsResourceKind } from '@/types/logistics';

const { t } = useTranslations();

type TabKey = LogisticsResourceKind;

const activeTab = ref<TabKey>('routes');

const tabs: { key: TabKey; label: string }[] = [
    { key: 'routes', label: t('Rutas') },
    { key: 'providers', label: t('Proveedores') },
    { key: 'hotels', label: t('Hoteles') },
];

const routeFields: LogisticsField[] = [
    {
        key: 'name',
        label: t('Nombre'),
        type: 'text',
        required: true,
        fullWidth: true,
    },
    {
        key: 'description',
        label: t('Descripción'),
        type: 'textarea',
        placeholder: t('Detalles de la ruta'),
    },
    { key: 'distance_km', label: t('Distancia (km)'), type: 'number' },
    { key: 'duration_hours', label: t('Duración (horas)'), type: 'number' },
];

const providerFields: LogisticsField[] = [
    {
        key: 'name',
        label: t('Nombre'),
        type: 'text',
        required: true,
        fullWidth: true,
    },
    {
        key: 'service_type',
        label: t('Tipo de servicio'),
        type: 'text',
        placeholder: t('Transporte, alimentación…'),
    },
    { key: 'contact_name', label: t('Contacto'), type: 'text' },
    { key: 'contact_phone', label: t('Teléfono'), type: 'text' },
    { key: 'contact_email', label: t('Email'), type: 'email' },
    { key: 'notes', label: t('Notas'), type: 'textarea' },
];

const hotelFields: LogisticsField[] = [
    {
        key: 'name',
        label: t('Nombre'),
        type: 'text',
        required: true,
        fullWidth: true,
    },
    { key: 'address', label: t('Dirección'), type: 'text', fullWidth: true },
    { key: 'contact_phone', label: t('Teléfono'), type: 'text' },
    { key: 'contact_email', label: t('Email'), type: 'email' },
    { key: 'notes', label: t('Notas'), type: 'textarea' },
];
</script>

<template>
    <Head :title="$t('Logística')" />

    <div class="px-4 py-6 md:px-8">
        <Heading
            :title="$t('Logística')"
            :description="
                $t(
                    'Administra las rutas, proveedores y hoteles que reutilizás en tus salidas.',
                )
            "
        />

        <div class="mt-6 max-w-3xl space-y-6">
            <div class="flex gap-1 rounded-lg bg-muted p-1">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition"
                    :class="
                        activeTab === tab.key
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="activeTab = tab.key"
                >
                    {{ tab.label }}
                </button>
            </div>

            <LogisticsCrudPanel
                v-if="activeTab === 'routes'"
                kind="routes"
                :empty-label="$t('Aún no tienes rutas')"
                :fields="routeFields"
            />
            <LogisticsCrudPanel
                v-else-if="activeTab === 'providers'"
                kind="providers"
                :empty-label="$t('Aún no tienes proveedores')"
                :fields="providerFields"
            />
            <LogisticsCrudPanel
                v-else
                kind="hotels"
                :empty-label="$t('Aún no tienes hoteles')"
                :fields="hotelFields"
            />
        </div>
    </div>
</template>
