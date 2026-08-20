<script setup lang="ts">
import { Info } from 'lucide-vue-next';
import { computed, ref, toRef } from 'vue';
import TourRouteLegend from '@/components/molecules/TourRouteLegend.vue';
import TourRouteStopList from '@/components/molecules/TourRouteStopList.vue';
import { Button } from '@/components/ui/button';
import { useTourRouteMap } from '@/composables/useTourRouteMap';
import { useTranslations } from '@/composables/useTranslations';
import { googleDirectionsUrl } from '@/lib/tour-route';
import type {
    TourRouteStop,
    TourRouteView,
    TourRouteZone,
} from '@/types/tour-route';

const props = withDefaults(
    defineProps<{
        stops: TourRouteStop[];
        zone?: TourRouteZone | null;
        note?: string | null;
    }>(),
    { zone: null, note: null },
);

const { t } = useTranslations();

const mapContainer = ref<HTMLElement | null>(null);

const {
    status,
    selectedStopIndex,
    activeView,
    availableViews,
    selectStop,
    showView,
    mount,
} = useTourRouteMap({
    container: mapContainer,
    stops: toRef(props, 'stops'),
    zone: toRef(props, 'zone'),
});

const viewLabels: Record<TourRouteView, string> = {
    all: t('Ruta completa'),
    pickup: t('Recogida'),
    site: t('Zona del tour'),
    drop: t('Regreso'),
};

const hasPickup = computed(() =>
    props.stops.some((stop) => stop.kind === 'pickup'),
);
const hasSites = computed(() =>
    props.stops.some((stop) => stop.kind === 'site'),
);
const hasDrop = computed(() =>
    props.stops.some((stop) => stop.kind === 'drop'),
);
const directionsUrl = computed(() => googleDirectionsUrl(props.stops));

defineExpose({ selectStop });
</script>

<template>
    <section id="ruta" class="min-w-0 scroll-mt-[70px] space-y-4">
        <div>
            <h2 class="text-[26px] font-semibold tracking-tight">
                {{ $t('Ruta y puntos de encuentro') }}
            </h2>
            <p class="mt-1 text-[13.5px] text-muted-foreground">
                {{
                    $t(
                        'Recogida, zona del tour, ruta completa y punto de regreso. Toca cualquier parada para centrarla.',
                    )
                }}
            </p>
        </div>

        <div
            v-if="availableViews.length > 0"
            class="-mx-1 overflow-x-auto px-1"
        >
            <div
                role="tablist"
                class="flex w-max gap-1.5 rounded-full bg-brand-green-100 p-1"
            >
                <button
                    v-for="view in availableViews"
                    :key="view"
                    type="button"
                    role="tab"
                    :aria-selected="activeView === view"
                    class="rounded-full px-4 py-2 text-[13px] font-semibold text-brand-green transition aria-selected:bg-brand-ink aria-selected:text-brand-cream"
                    @click="showView(view)"
                >
                    {{ viewLabels[view] }}
                </button>
            </div>
        </div>

        <div
            class="grid overflow-hidden rounded-2xl border border-border bg-card lg:grid-cols-[290px_minmax(0,1fr)]"
        >
            <TourRouteStopList
                :stops="stops"
                :selected-index="selectedStopIndex"
                @select="selectStop"
            />

            <div class="relative h-[470px] w-full">
                <div ref="mapContainer" class="z-[1] h-full w-full" />

                <div
                    v-if="status === 'loading'"
                    class="absolute inset-0 z-[2] animate-pulse bg-muted"
                />

                <div
                    v-else-if="status === 'error'"
                    class="absolute inset-0 z-[2] flex flex-col items-center justify-center gap-3 bg-muted px-6 text-center"
                >
                    <p class="text-sm text-muted-foreground">
                        {{ $t('No se pudo cargar el mapa.') }}
                    </p>
                    <Button variant="outline" size="sm" @click="mount">
                        {{ $t('Reintentar') }}
                    </Button>
                </div>
            </div>

            <TourRouteLegend
                :has-pickup="hasPickup"
                :has-sites="hasSites"
                :has-drop="hasDrop"
                :directions-url="directionsUrl"
            />
        </div>

        <div
            v-if="note"
            class="flex items-start gap-3 rounded-xl bg-accent px-4 py-3.5 text-sm text-accent-foreground"
        >
            <Info class="mt-0.5 size-4 shrink-0" />
            <p>{{ note }}</p>
        </div>
    </section>
</template>
