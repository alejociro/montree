import type { Ref } from 'vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { loadLeaflet } from '@/composables/useLeaflet';
import { translate } from '@/composables/useTranslations';
import {
    deriveZoneFromStops,
    routeColor,
    stopColor,
    stopCoordinates,
    stopIndexesOfKind,
} from '@/lib/tour-route';
import type {
    LeafletDivIcon,
    LeafletMap,
    LeafletMarker,
    LeafletNamespace,
} from '@/types/leaflet';
import type {
    TourRouteMapStatus,
    TourRouteStop,
    TourRouteView,
    TourRouteZone,
} from '@/types/tour-route';

const TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const TILE_ATTRIBUTION = '© OpenStreetMap contributors';
const FLY_ZOOM = 14.5;
const ENDPOINT_ZOOM = 15;
const FLY_DURATION_SECONDS = 0.7;
const POPUP_DELAY_MS = 750;
/** Debajo de este número las paradas del recorrido caben separadas en la vista general. */
const MIN_STOPS_TO_GROUP = 3;

type UseTourRouteMapOptions = {
    container: Ref<HTMLElement | null>;
    stops: Ref<TourRouteStop[]>;
    zone: Ref<TourRouteZone | null>;
};

export type UseTourRouteMapReturn = {
    status: Ref<TourRouteMapStatus>;
    selectedStopIndex: Ref<number | null>;
    activeView: Ref<TourRouteView>;
    availableViews: Ref<TourRouteView[]>;
    selectStop: (index: number, fly?: boolean) => void;
    showView: (view: TourRouteView) => void;
    /**
     * Re-mide el contenedor y vuelve a encuadrar. Obligatorio cuando el mapa
     * se montó dentro de una pestaña oculta: Leaflet mide 0×0 y el mapa queda
     * en gris hasta que alguien lo redimensiona.
     */
    fit: () => void;
    mount: () => void;
};

function markerHtml(stop: TourRouteStop, size: number): string {
    return `<div class="tour-route-pin" style="width:${size}px;height:${size}px;background:${stopColor(stop)}">${stop.code}</div>`;
}

export function useTourRouteMap(
    options: UseTourRouteMapOptions,
): UseTourRouteMapReturn {
    const status = ref<TourRouteMapStatus>('loading');
    const selectedStopIndex = ref<number | null>(null);
    const activeView = ref<TourRouteView>('all');

    let leaflet: LeafletNamespace | null = null;
    let map: LeafletMap | null = null;
    let markers: LeafletMarker[] = [];
    let clusterMarker: LeafletMarker | null = null;
    let grouped: boolean | null = null;
    let resizeObserver: ResizeObserver | null = null;

    const pickupIndex = computed(() =>
        options.stops.value.findIndex((stop) => stop.kind === 'pickup'),
    );
    const dropIndex = computed(() =>
        options.stops.value.findIndex((stop) => stop.kind === 'drop'),
    );
    const siteIndexes = computed(() =>
        stopIndexesOfKind(options.stops.value, 'site'),
    );
    const isGroupable = computed(
        () => siteIndexes.value.length >= MIN_STOPS_TO_GROUP,
    );

    const availableViews = computed<TourRouteView[]>(() => {
        const views: TourRouteView[] = ['all'];

        if (pickupIndex.value >= 0) {
            views.push('pickup');
        }

        if (siteIndexes.value.length > 0) {
            views.push('site');
        }

        if (dropIndex.value >= 0) {
            views.push('drop');
        }

        return views.length > 2 ? views : [];
    });

    function endpointIcon(
        library: LeafletNamespace,
        stop: TourRouteStop,
        side: 'left' | 'right',
        offsetX: number,
        offsetY: number,
    ): LeafletDivIcon {
        const pin = markerHtml(stop, 30);
        const chip = `<span class="tour-route-chip">${stop.label ?? stop.name}</span>`;
        const anchorX = side === 'left' ? 135 : 15;

        return library.divIcon({
            className: '',
            iconSize: [150, 30],
            iconAnchor: [anchorX + offsetX, 15 + offsetY],
            html: `<div class="tour-route-endpoint" style="justify-content:${side === 'left' ? 'flex-end' : 'flex-start'}">${side === 'left' ? chip + pin : pin + chip}</div>`,
        });
    }

    function dotIcon(
        library: LeafletNamespace,
        stop: TourRouteStop,
        size: number,
    ): LeafletDivIcon {
        return library.divIcon({
            className: '',
            iconSize: [size, size],
            iconAnchor: [size / 2, size / 2],
            html: markerHtml(stop, size),
        });
    }

    function setGrouped(shouldGroup: boolean): void {
        const shouldCollapse = shouldGroup && isGroupable.value;

        if (
            map === null ||
            clusterMarker === null ||
            shouldCollapse === grouped
        ) {
            return;
        }

        const target = map;
        grouped = shouldCollapse;

        if (shouldCollapse) {
            siteIndexes.value.forEach((index) => {
                const marker = markers[index];

                if (marker !== undefined) {
                    target.removeLayer(marker);
                }
            });
            clusterMarker.addTo(target);

            return;
        }

        target.removeLayer(clusterMarker);
        siteIndexes.value.forEach((index) => {
            markers[index]?.addTo(target);
        });
    }

    function selectStop(index: number, fly = true): void {
        const stop = options.stops.value[index];

        if (stop === undefined || map === null) {
            return;
        }

        selectedStopIndex.value = index;
        setGrouped(false);

        if (fly) {
            map.flyTo(stopCoordinates(stop), FLY_ZOOM, {
                duration: FLY_DURATION_SECONDS,
            });
        }

        window.setTimeout(
            () => markers[index]?.openPopup(),
            fly ? POPUP_DELAY_MS : 0,
        );
    }

    function showView(view: TourRouteView): void {
        if (map === null || leaflet === null) {
            return;
        }

        activeView.value = view;
        const stops = options.stops.value;

        if (view === 'pickup' && pickupIndex.value >= 0) {
            setGrouped(true);
            map.flyTo(
                stopCoordinates(stops[pickupIndex.value]),
                ENDPOINT_ZOOM,
                { duration: FLY_DURATION_SECONDS },
            );

            return;
        }

        if (view === 'drop' && dropIndex.value >= 0) {
            setGrouped(true);
            map.flyTo(stopCoordinates(stops[dropIndex.value]), ENDPOINT_ZOOM, {
                duration: FLY_DURATION_SECONDS,
            });

            return;
        }

        if (view === 'site' && siteIndexes.value.length > 0) {
            setGrouped(false);
            map.fitBounds(
                leaflet
                    .latLngBounds(
                        siteIndexes.value.map((index) =>
                            stopCoordinates(stops[index]),
                        ),
                    )
                    .pad(0.35),
            );

            return;
        }

        setGrouped(true);
        map.fitBounds(
            leaflet.latLngBounds(stops.map(stopCoordinates)).pad(0.3),
        );
    }

    function drawLayers(library: LeafletNamespace, target: LeafletMap): void {
        const stops = options.stops.value;
        const sites = siteIndexes.value;
        const firstSite = sites[0];
        const lastSite = sites[sites.length - 1];

        if (pickupIndex.value >= 0 && firstSite !== undefined) {
            library
                .polyline(
                    [
                        stopCoordinates(stops[pickupIndex.value]),
                        stopCoordinates(stops[firstSite]),
                    ],
                    {
                        color: routeColor('transfer'),
                        weight: 3,
                        dashArray: '6 8',
                    },
                )
                .addTo(target);
        }

        if (sites.length >= 2) {
            library
                .polyline(
                    sites.map((index) => stopCoordinates(stops[index])),
                    { color: routeColor('pickup'), weight: 4 },
                )
                .addTo(target);
        }

        const returnOrigin =
            lastSite ??
            (pickupIndex.value >= 0 ? pickupIndex.value : undefined);

        if (dropIndex.value >= 0 && returnOrigin !== undefined) {
            library
                .polyline(
                    [
                        stopCoordinates(stops[returnOrigin]),
                        stopCoordinates(stops[dropIndex.value]),
                    ],
                    {
                        color: routeColor('drop'),
                        weight: 3,
                        dashArray: '6 8',
                    },
                )
                .addTo(target);
        }

        const zone = options.zone.value ?? deriveZoneFromStops(stops);

        if (zone !== null) {
            library
                .circle([zone.latitude, zone.longitude], {
                    radius: zone.radius_meters,
                    color: routeColor('zone'),
                    weight: 1,
                    fillColor: routeColor('zone'),
                    fillOpacity: 0.07,
                })
                .addTo(target);
        }
    }

    function buildMarkers(library: LeafletNamespace, target: LeafletMap): void {
        const stops = options.stops.value;

        markers = stops.map((stop, index) => {
            const icon =
                index === pickupIndex.value
                    ? endpointIcon(library, stop, 'left', 18, -8)
                    : index === dropIndex.value
                      ? endpointIcon(library, stop, 'right', -18, 12)
                      : dotIcon(
                            library,
                            stop,
                            index === siteIndexes.value[0] ? 30 : 26,
                        );

            const popupLines = [
                `<b>${stop.name}</b>`,
                stop.place,
                stop.time === null
                    ? null
                    : `<span style="color:${routeColor('transfer')}">${stop.time}</span>`,
            ].filter((line): line is string => line !== null);

            return library
                .marker(stopCoordinates(stop), {
                    icon,
                    zIndexOffset: index * 10,
                })
                .bindPopup(popupLines.join('<br>'))
                .on('click', () => selectStop(index, false));
        });

        stops.forEach((stop, index) => {
            if (stop.kind !== 'site') {
                markers[index]?.addTo(target);
            }
        });

        if (!isGroupable.value) {
            siteIndexes.value.forEach((index) => markers[index]?.addTo(target));

            return;
        }

        const first = stops[siteIndexes.value[0]];
        const last = stops[siteIndexes.value[siteIndexes.value.length - 1]];
        const label = translate(':count paradas del recorrido', {
            count: siteIndexes.value.length,
        });

        clusterMarker = library
            .marker(
                [
                    (first.latitude + last.latitude) / 2,
                    (first.longitude + last.longitude) / 2,
                ],
                {
                    zIndexOffset: 60,
                    icon: library.divIcon({
                        className: '',
                        iconSize: [260, 30],
                        iconAnchor: [15, 15],
                        html: `<div class="tour-route-endpoint" style="justify-content:flex-start"><div class="tour-route-pin" style="width:30px;height:30px;background:${routeColor('site')}">•</div><span class="tour-route-chip">${label}</span></div>`,
                    }),
                },
            )
            .on('click', () => showView('site'));
    }

    async function mount(): Promise<void> {
        const element = options.container.value;

        if (element === null || options.stops.value.length === 0) {
            return;
        }

        status.value = 'loading';

        try {
            leaflet = await loadLeaflet();
        } catch {
            status.value = 'error';

            return;
        }

        map = leaflet.map(element, {
            scrollWheelZoom: false,
            zoomControl: true,
        });
        leaflet
            .tileLayer(TILE_URL, { maxZoom: 18, attribution: TILE_ATTRIBUTION })
            .addTo(map);

        drawLayers(leaflet, map);
        buildMarkers(leaflet, map);
        showView('all');

        // El scroll del mouse secuestra la página: solo se habilita tras hacer clic.
        map.on('click', () => map?.scrollWheelZoom.enable());
        map.on('mouseout', () => map?.scrollWheelZoom.disable());

        status.value = 'ready';

        // WHY: Leaflet mide el contenedor al montar. En el detalle de tour el mapa
        // vive en una columna más angosta que la ventana, así que la primera medida
        // llega corta y el encuadre queda desplazado: hay que re-medir y volver a
        // aplicar la vista. El observer cubre el resto de cambios de ancho.
        window.setTimeout(() => {
            map?.invalidateSize();
            showView(activeView.value);
        }, 0);

        resizeObserver = new ResizeObserver(() => map?.invalidateSize());
        resizeObserver.observe(element);
    }

    watch(
        options.container,
        (element) => {
            if (element !== null && map === null) {
                void mount();
            }
        },
        { immediate: true },
    );

    onBeforeUnmount(() => {
        resizeObserver?.disconnect();
        resizeObserver = null;
        map?.remove();
        map = null;
        markers = [];
        clusterMarker = null;
        grouped = null;
    });

    function fit(): void {
        if (map === null) {
            return;
        }

        map.invalidateSize();
        showView(activeView.value);
    }

    return {
        status,
        selectedStopIndex,
        activeView,
        availableViews,
        selectStop,
        showView,
        fit,
        mount: () => void mount(),
    };
}
