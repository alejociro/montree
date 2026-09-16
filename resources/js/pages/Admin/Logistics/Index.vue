<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { index as logisticsIndex } from '@/actions/App/Http/Controllers/Admin/LogisticsPagesController';
import Heading from '@/components/Heading.vue';
import type { CountTab } from '@/components/molecules/CountTabs.vue';
import FilterBar from '@/components/molecules/FilterBar.vue';
import LogisticsCrudPanel from '@/components/organisms/LogisticsCrudPanel.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type {
    HotelResource,
    LogisticsCatalogKind,
    LogisticsPaginatedResponse,
    ProviderResource,
} from '@/types/logistics';

const { t } = useTranslations();

type TabKey = LogisticsCatalogKind;

type Props = {
    providers: LogisticsPaginatedResponse<ProviderResource>;
    hotels: LogisticsPaginatedResponse<HotelResource>;
    filters: { search: string | null; tab: TabKey };
};

const props = defineProps<Props>();

const activeTab = ref<TabKey>(props.filters.tab);
const search = ref(props.filters.search ?? '');

/**
 * Los dos catálogos llegan con la página —aunque solo uno esté visible— para
 * que la pestaña lleve su conteo: un contador que solo aparece al abrir la
 * bandeja no sirve para decidir a cuál ir.
 */
const counts = computed<Record<TabKey, number>>(() => ({
    providers: props.providers.meta.total,
    hotels: props.hotels.meta.total,
}));

const TAB_LABELS: Record<TabKey, string> = {
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
    providers: t('Nuevo proveedor'),
    hotels: t('Nuevo hotel'),
};

/** La acción principal es la del tab activo, no una lista de tres botones. */
const newLabel = computed(() => NEW_LABELS[activeTab.value]);

const resultLabel = computed<string>(() =>
    t(':count fichas', { count: counts.value[activeTab.value] }),
);

const VISIT_OPTIONS = {
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: ['providers', 'hotels', 'filters'],
};

/** Un término nuevo empieza en la primera página de los dos catálogos. */
const applySearch = useDebounceFn(() => {
    const term = search.value.trim();

    router.get(
        logisticsIndex.url({
            query: {
                search: term === '' ? null : term,
                tab: activeTab.value,
            },
        }),
        {},
        VISIT_OPTIONS,
    );
}, 300);

watch(search, () => void applySearch());

watch(activeTab, (tab) => {
    router.get(logisticsIndex.url({ mergeQuery: { tab } }), {}, VISIT_OPTIONS);
});

const panels = {
    providers: ref<InstanceType<typeof LogisticsCrudPanel> | null>(null),
    hotels: ref<InstanceType<typeof LogisticsCrudPanel> | null>(null),
};

function createInActiveTab(): void {
    panels[activeTab.value].value?.openCreate();
}
</script>

<template>
    <Head :title="$t('Logística')" />

    <div class="px-4 py-6 md:px-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="$t('Logística')"
                :description="
                    $t(
                        'Proveedores y hoteles que reutilizas al armar cada salida.',
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
            :placeholder="
                $t('Buscar ficha por nombre, municipio, contacto o tarifa')
            "
            :result-label="resultLabel"
            :tabs="tabs"
            :active-tab="activeTab"
            :tabs-label="$t('Catálogos de logística')"
            @update:search="search = $event"
            @update:active-tab="activeTab = $event as TabKey"
        />

        <div class="mt-5">
            <LogisticsCrudPanel
                v-show="activeTab === 'providers'"
                :ref="panels.providers"
                kind="providers"
                :records="props.providers.data"
                :meta="props.providers.meta"
                page-name="providers_page"
                :empty-label="$t('Aún no tienes proveedores')"
            />
            <LogisticsCrudPanel
                v-show="activeTab === 'hotels'"
                :ref="panels.hotels"
                kind="hotels"
                :records="props.hotels.data"
                :meta="props.hotels.meta"
                page-name="hotels_page"
                :empty-label="$t('Aún no tienes hoteles')"
            />
        </div>
    </div>
</template>
