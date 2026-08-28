<script setup lang="ts">
import { Crosshair, MapPin, Pencil, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import PlaceSearchField from '@/components/molecules/PlaceSearchField.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { SavedPlace } from '@/lib/tour-itinerary';
import type { GeocodedPlace } from '@/types/geocoding';

/**
 * Selector de ubicación con TRES caminos y ninguna coordenada a la vista
 * (handoff 09): buscar la dirección, reutilizar un lugar ya guardado del tour,
 * o señalar el pin en el mapa.
 *
 * WHY: el formulario pedía latitud y longitud. Quien programa el tour sabe
 * decir «la plaza de Salento», no «4.6376, -75.5706». Cuando ya hay ubicación
 * se muestra resuelta —nombre y dirección— y solo se reabre con «Cambiar»: el
 * caso normal es que ya esté bien y el buscador solo estorbe.
 */
const { t } = useTranslations();

type Props = {
    name: string;
    address: string;
    latitude: string;
    longitude: string;
    /** Lugares ya usados en este tour, para compartir el mismo punto. */
    savedPlaces?: SavedPlace[];
    /** Número de la parada dueña de este campo (1-based), para no ofrecerse a sí misma. */
    ownNumber?: number | null;
    targetLabel: string;
    canFocus?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    savedPlaces: () => [],
    ownNumber: null,
    canFocus: true,
});

const emit = defineEmits<{
    (e: 'select', place: GeocodedPlace): void;
    (e: 'reuse', place: SavedPlace): void;
    (e: 'focus'): void;
    (e: 'clear'): void;
}>();

const editing = ref(false);

const hasPlace = computed(
    () =>
        Number.isFinite(Number.parseFloat(props.latitude)) &&
        Number.isFinite(Number.parseFloat(props.longitude)),
);

const title = computed(() =>
    props.name.trim() !== ''
        ? props.name
        : props.address.trim() !== ''
          ? props.address
          : t('Ubicación sin nombre'),
);

/** Los demás lugares del tour: reutilizar el propio no cambia nada. */
const reusable = computed(() =>
    props.savedPlaces.filter(
        (place) =>
            props.ownNumber === null || !place.usedBy.includes(props.ownNumber),
    ),
);

function choose(place: GeocodedPlace): void {
    emit('select', place);
    editing.value = false;
}

function reuse(place: SavedPlace): void {
    emit('reuse', place);
    editing.value = false;
}
</script>

<template>
    <div>
        <div
            v-if="hasPlace && !editing"
            class="flex flex-wrap items-center gap-2.5 rounded-xl border border-primary/25 bg-primary-soft px-3 py-2.5"
        >
            <MapPin class="size-4 shrink-0 text-primary-readable" />
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-semibold">
                    {{ title }}
                </span>
                <span
                    v-if="props.address"
                    class="block truncate text-xs text-muted-foreground"
                >
                    {{ props.address }}
                </span>
            </span>
            <button
                v-if="props.canFocus"
                type="button"
                class="text-[13px] font-medium text-primary-readable underline-offset-4 hover:underline"
                @click="emit('focus')"
            >
                {{ $t('Ver en el mapa') }}
            </button>
            <Button
                type="button"
                size="sm"
                variant="outline"
                @click="editing = true"
            >
                <Pencil class="size-3.5" />
                {{ $t('Cambiar') }}
            </Button>
        </div>

        <div v-else class="space-y-2.5">
            <div class="flex items-center justify-between gap-2">
                <MonoLabel>{{ $t('Ubicación') }}</MonoLabel>
                <Button
                    v-if="hasPlace"
                    type="button"
                    size="sm"
                    variant="ghost"
                    @click="editing = false"
                >
                    <X class="size-3.5" />
                    {{ $t('Conservar la actual') }}
                </Button>
            </div>

            <PlaceSearchField
                :target-label="props.targetLabel"
                @select="choose"
            />

            <div v-if="reusable.length > 0">
                <MonoLabel>{{ $t('O reutiliza un lugar del tour') }}</MonoLabel>
                <ul class="mt-1.5 flex flex-wrap gap-1.5">
                    <li v-for="place in reusable" :key="place.key">
                        <button
                            type="button"
                            class="inline-flex max-w-[280px] items-center gap-1.5 rounded-full border border-border px-3 py-1.5 text-[13px] transition hover:bg-primary-soft focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            @click="reuse(place)"
                        >
                            <MapPin
                                class="size-3.5 shrink-0 text-muted-foreground"
                            />
                            <span class="truncate">{{ place.name }}</span>
                            <span class="shrink-0 text-muted-foreground">
                                {{
                                    $t('· paso :steps', {
                                        steps: place.usedBy.join(', '),
                                    })
                                }}
                            </span>
                        </button>
                    </li>
                </ul>
            </div>

            <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
                <Crosshair class="size-3.5" />
                {{
                    $t(
                        'O señálalo en el mapa: haz clic donde va, o arrastra su pin.',
                    )
                }}
            </p>
        </div>
    </div>
</template>
