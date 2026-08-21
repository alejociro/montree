<script setup lang="ts">
import { Loader2, MapPin, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { useGeocoder } from '@/composables/useGeocoder';
import type { GeocodedPlace } from '@/types/geocoding';

/**
 * Buscador de direcciones del editor de ruta.
 *
 * WHY: el formulario pedía latitud y longitud. Quien programa un tour sabe que
 * recoge «en la plaza de Salento, frente a la iglesia»; nadie sabe que eso es
 * 4.6376, -75.5706. Aquí se escribe la dirección y el punto sale solo — y si la
 * dirección no existe en ningún callejero, el mapa de al lado sigue aceptando
 * un clic.
 */
const props = withDefaults(
    defineProps<{
        /** A qué punto se le va a asignar el resultado. */
        targetLabel: string;
        disabled?: boolean;
    }>(),
    { disabled: false },
);

const emit = defineEmits<{
    (e: 'select', place: GeocodedPlace): void;
}>();

const { results, loading, error, search, clear } = useGeocoder();

const term = ref('');
const open = ref(false);

watch(term, (value) => {
    open.value = value.trim().length >= 3;
    search(value);
});

function choose(place: GeocodedPlace): void {
    emit('select', place);
    term.value = '';
    open.value = false;
    clear();
}
</script>

<template>
    <div class="relative">
        <div class="relative">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
            />
            <Input
                v-model="term"
                type="search"
                class="pl-9"
                :disabled="props.disabled"
                :placeholder="
                    $t('Buscar dirección o lugar para :target', {
                        target: props.targetLabel,
                    })
                "
                :aria-label="$t('Buscar una dirección')"
                @keydown.escape="open = false"
            />
            <Loader2
                v-if="loading"
                class="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-muted-foreground"
            />
        </div>

        <div
            v-if="open && (results.length > 0 || error)"
            class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-border bg-popover shadow-lg"
        >
            <p v-if="error" class="px-3 py-2.5 text-sm text-muted-foreground">
                {{ error }}
            </p>
            <ul v-else>
                <li v-for="place in results" :key="place.label">
                    <button
                        type="button"
                        class="flex w-full items-start gap-2 px-3 py-2.5 text-left text-sm transition hover:bg-primary-soft focus-visible:bg-primary-soft focus-visible:outline-none"
                        @click="choose(place)"
                    >
                        <MapPin
                            class="mt-0.5 size-4 shrink-0 text-primary-readable"
                        />
                        <span class="min-w-0">
                            <span class="block font-medium">{{
                                place.name
                            }}</span>
                            <span
                                class="block truncate text-xs text-muted-foreground"
                                >{{ place.label }}</span
                            >
                        </span>
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>
