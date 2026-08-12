<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import LogisticsCrudPanel from '@/components/organisms/LogisticsCrudPanel.vue';
import type { LogisticsField, LogisticsResourceKind } from '@/types/logistics';

type TabKey = LogisticsResourceKind;

const activeTab = ref<TabKey>('routes');

const tabs: { key: TabKey; label: string }[] = [
    { key: 'routes', label: 'Rutas' },
    { key: 'providers', label: 'Proveedores' },
    { key: 'hotels', label: 'Hoteles' },
];

const routeFields: LogisticsField[] = [
    { key: 'name', label: 'Nombre', type: 'text', required: true, fullWidth: true },
    {
        key: 'description',
        label: 'Descripción',
        type: 'textarea',
        placeholder: 'Detalles de la ruta',
    },
    { key: 'distance_km', label: 'Distancia (km)', type: 'number' },
    { key: 'duration_hours', label: 'Duración (horas)', type: 'number' },
];

const providerFields: LogisticsField[] = [
    { key: 'name', label: 'Nombre', type: 'text', required: true, fullWidth: true },
    {
        key: 'service_type',
        label: 'Tipo de servicio',
        type: 'text',
        placeholder: 'Transporte, alimentación…',
    },
    { key: 'contact_name', label: 'Contacto', type: 'text' },
    { key: 'contact_phone', label: 'Teléfono', type: 'text' },
    { key: 'contact_email', label: 'Email', type: 'email' },
    { key: 'notes', label: 'Notas', type: 'textarea' },
];

const hotelFields: LogisticsField[] = [
    { key: 'name', label: 'Nombre', type: 'text', required: true, fullWidth: true },
    { key: 'address', label: 'Dirección', type: 'text', fullWidth: true },
    { key: 'contact_phone', label: 'Teléfono', type: 'text' },
    { key: 'contact_email', label: 'Email', type: 'email' },
    { key: 'notes', label: 'Notas', type: 'textarea' },
];
</script>

<template>
    <Head title="Logística" />

    <div class="px-4 py-6 md:px-8">
        <Heading
            title="Logística"
            description="Administra las rutas, proveedores y hoteles que reutilizás en tus salidas."
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
                singular="Ruta"
                feminine
                empty-label="Aún no tienes rutas"
                :fields="routeFields"
            />
            <LogisticsCrudPanel
                v-else-if="activeTab === 'providers'"
                kind="providers"
                singular="Proveedor"
                empty-label="Aún no tienes proveedores"
                :fields="providerFields"
            />
            <LogisticsCrudPanel
                v-else
                kind="hotels"
                singular="Hotel"
                empty-label="Aún no tienes hoteles"
                :fields="hotelFields"
            />
        </div>
    </div>
</template>
