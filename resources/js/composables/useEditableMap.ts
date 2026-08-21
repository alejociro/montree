import type { Ref } from 'vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import { loadLeaflet } from '@/composables/useLeaflet';
import { readableInk } from '@/lib/color';
import type { MapPoint } from '@/types/geocoding';
import type {
    LeafletMap,
    LeafletMarker,
    LeafletNamespace,
} from '@/types/leaflet';

/** Un punto dibujable del editor: puede no tener coordenadas todavía. */
export type EditablePoint = {
    /** Índice estable dentro del formulario; viaja en los eventos. */
    id: number;
    label: string;
    color: string;
    latitude: number | null;
    longitude: number | null;
};

export type UseEditableMapOptions = {
    container: Ref<HTMLElement | null>;
    points: Ref<EditablePoint[]>;
    /** Punto activo: se resalta y el mapa lo centra al cambiar. */
    activeId: Ref<number | null>;
    /** Arrastrar un pin o hacer clic en el mapa mueve el punto activo. */
    onMove: (id: number, point: MapPoint) => void;
};

export type UseEditableMapReturn = {
    ready: Ref<boolean>;
    /** Centra el mapa en un punto concreto sin cambiar el zoom. */
    focus: (id: number) => void;
    /** Encuadra todos los puntos con coordenadas. */
    fit: () => void;
};

const FALLBACK_CENTER: [number, number] = [4.5709, -74.2973];
const FALLBACK_ZOOM = 5;
const FOCUS_ZOOM = 15;

function pinHtml(point: EditablePoint, active: boolean): string {
    const size = active ? 32 : 26;

    return `<div class="tour-route-pin" style="width:${size}px;height:${size}px;background:${point.color};color:${readableInk(point.color)};${active ? 'outline:3px solid var(--ring);outline-offset:2px' : ''}">${point.label}</div>`;
}

/**
 * Mapa EDITABLE del formulario de ruta: pines que se arrastran y un clic que
 * coloca el punto activo.
 *
 * WHY separado de `useTourRouteMap`: ese dibuja la ruta terminada —polilíneas,
 * zona, vistas por tramo— y es de solo lectura. Mezclar la edición ahí habría
 * significado que el mapa público cargara la lógica de arrastre. Este solo
 * sabe de puntos y de moverlos.
 */
export function useEditableMap(
    options: UseEditableMapOptions,
): UseEditableMapReturn {
    const ready = ref(false);

    let leaflet: LeafletNamespace | null = null;
    let map: LeafletMap | null = null;
    let markers = new Map<number, LeafletMarker>();
    let hasFitted = false;

    function placed(): EditablePoint[] {
        return options.points.value.filter(
            (point) => point.latitude !== null && point.longitude !== null,
        );
    }

    function render(): void {
        if (leaflet === null || map === null) {
            return;
        }

        const library = leaflet;
        const instance = map;
        const seen = new Set<number>();

        for (const point of options.points.value) {
            if (point.latitude === null || point.longitude === null) {
                continue;
            }

            seen.add(point.id);

            const active = options.activeId.value === point.id;
            const icon = library.divIcon({
                className: '',
                iconSize: [active ? 32 : 26, active ? 32 : 26],
                iconAnchor: [active ? 16 : 13, active ? 16 : 13],
                html: pinHtml(point, active),
            });

            const existing = markers.get(point.id);

            if (existing) {
                existing.setLatLng([point.latitude, point.longitude]);
                existing.setIcon(icon);

                continue;
            }

            const marker = library
                .marker([point.latitude, point.longitude], {
                    icon,
                    draggable: true,
                    keyboard: true,
                    title: point.label,
                })
                .addTo(instance);

            marker.on('dragend', () => {
                const position = marker.getLatLng();
                options.onMove(point.id, {
                    latitude: position.lat,
                    longitude: position.lng,
                });
            });

            markers.set(point.id, marker);
        }

        for (const [id, marker] of markers) {
            if (!seen.has(id)) {
                marker.remove();
                markers.delete(id);
            }
        }

        if (!hasFitted && placed().length > 0) {
            hasFitted = true;
            fit();
        }
    }

    function fit(): void {
        if (leaflet === null || map === null) {
            return;
        }

        const coordinates = placed().map(
            (point) => [point.latitude, point.longitude] as [number, number],
        );

        if (coordinates.length === 0) {
            map.setView(FALLBACK_CENTER, FALLBACK_ZOOM);

            return;
        }

        if (coordinates.length === 1) {
            map.setView(coordinates[0], FOCUS_ZOOM);

            return;
        }

        map.fitBounds(leaflet.latLngBounds(coordinates), { padding: [40, 40] });
    }

    function focus(id: number): void {
        const point = options.points.value.find((item) => item.id === id);

        if (
            map === null ||
            point === undefined ||
            point.latitude === null ||
            point.longitude === null
        ) {
            return;
        }

        map.setView(
            [point.latitude, point.longitude],
            Math.max(map.getZoom(), FOCUS_ZOOM),
        );
    }

    async function mount(): Promise<void> {
        const element = options.container.value;

        if (element === null || map !== null) {
            return;
        }

        leaflet = await loadLeaflet();
        map = leaflet.map(element, { scrollWheelZoom: false });
        map.setView(FALLBACK_CENTER, FALLBACK_ZOOM);

        leaflet
            .tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 18,
                attribution: '© OpenStreetMap contributors',
            })
            .addTo(map);

        // Clic en el mapa: coloca el punto activo. Es la salida cuando la
        // dirección no existe en el buscador —un mirador, una finca— y quien
        // programa el tour sí sabe señalarlo.
        map.on('click', (event) => {
            const id = options.activeId.value;

            if (id === null) {
                return;
            }

            options.onMove(id, {
                latitude: event.latlng.lat,
                longitude: event.latlng.lng,
            });
        });

        ready.value = true;
        render();
    }

    watch(options.container, () => void mount(), { immediate: true });
    watch(options.points, render, { deep: true });
    watch(options.activeId, (id) => {
        render();

        if (id !== null) {
            focus(id);
        }
    });

    onBeforeUnmount(() => {
        map?.remove();
        map = null;
        leaflet = null;
        markers = new Map();
        ready.value = false;
    });

    return { ready, focus, fit };
}
