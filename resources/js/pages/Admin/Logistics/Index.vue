<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import type { CountTab } from '@/components/molecules/CountTabs.vue';
import FilterBar from '@/components/molecules/FilterBar.vue';
import LogisticsCrudPanel from '@/components/organisms/LogisticsCrudPanel.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { LogisticsField, LogisticsResourceKind } from '@/types/logistics';

const { t } = useTranslations();

type TabKey = LogisticsResourceKind;

const activeTab = ref<TabKey>('routes');
const search = ref('');

/**
 * Los tres paneles se montan a la vez (`v-show`) para poder poner el conteo en
 * su pestaña: un contador que solo aparece al abrir la bandeja no sirve para
 * decidir a cuál ir.
 */
const counts = ref<Record<TabKey, number | null>>({
    routes: null,
    providers: null,
    hotels: null,
});

const TAB_LABELS: Record<TabKey, string> = {
    routes: t('Rutas'),
    providers: t('Proveedores'),
    hotels: t('Hoteles'),
};

const tabs = computed<CountTab[]>(() =>
    (Object.keys(TAB_LABELS) as TabKey[]).map((key) => ({
        id: key,
        label: TAB_LABELS[key],
        count: counts.value[key],
    })),
);

const NEW_LABELS: Record<TabKey, string> = {
    routes: t('Nueva ruta'),
    providers: t('Nuevo proveedor'),
    hotels: t('Nuevo hotel'),
};

/** La acción principal es la del tab activo, no una lista de tres botones. */
const newLabel = computed(() => NEW_LABELS[activeTab.value]);

const resultLabel = computed<string | null>(() => {
    const value = counts.value[activeTab.value];

    return value === null ? null : t(':count fichas', { count: value });
});

const panels = {
    routes: ref<InstanceType<typeof LogisticsCrudPanel> | null>(null),
    providers: ref<InstanceType<typeof LogisticsCrudPanel> | null>(null),
    hotels: ref<InstanceType<typeof LogisticsCrudPanel> | null>(null),
};

function createInActiveTab(): void {
    panels[activeTab.value].value?.openCreate();
}

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
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="$t('Logística')"
                :description="
                    $t(
                        'Rutas, proveedores y hoteles que reutilizas en tus salidas.',
                    )
                "
            />
            <Button @click="createInActiveTab">
                <Plus class="size-4" />
                {{ newLabel }}
            </Button>
        </div>

        <FilterBar
            class="mt-5"
            search-id="logistics-search"
            :search="search"
            :placeholder="$t('Buscar ficha por nombre, contacto o servicio')"
            :result-label="resultLabel"
            :tabs="tabs"
            :active-tab="activeTab"
            :tabs-label="$t('Catálogos de logística')"
            @update:search="search = $event"
            @update:active-tab="activeTab = $event as TabKey"
        />

        <div class="mt-5">
            <LogisticsCrudPanel
                v-show="activeTab === 'routes'"
                :ref="panels.routes"
                kind="routes"
                :search="search"
                :empty-label="$t('Aún no tienes rutas')"
                :fields="routeFields"
                @update:count="counts.routes = $event"
            />
            <LogisticsCrudPanel
                v-show="activeTab === 'providers'"
                :ref="panels.providers"
                kind="providers"
                :search="search"
                :empty-label="$t('Aún no tienes proveedores')"
                :fields="providerFields"
                @update:count="counts.providers = $event"
            />
            <LogisticsCrudPanel
                v-show="activeTab === 'hotels'"
                :ref="panels.hotels"
                kind="hotels"
                :search="search"
                :empty-label="$t('Aún no tienes hoteles')"
                :fields="hotelFields"
                @update:count="counts.hotels = $event"
            />
        </div>
    </div>
</template>
