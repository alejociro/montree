<script setup lang="ts">
import { Check, Loader2, MapPin } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { useGeocoder } from '@/composables/useGeocoder';
import type { GeocodedPlace } from '@/types/geocoding';

/**
 * Campo de dirección de las fichas de logística.
 *
 * Nunca pide latitud ni longitud: se escribe la dirección, se elige una
 * sugerencia y el punto se guarda solo —junto con el municipio y el
 * departamento, que se rellenan de paso—. Si el callejero no la conoce, el
 * texto se guarda tal cual: una vereda sin nomenclatura sigue siendo una
 * dirección válida para el guía.
 */
const props = withDefaults(
    defineProps<{
        id: string;
        modelValue: string;
        placeholder?: string;
        /** Se ha guardado ya un punto para esta dirección. */
        located?: boolean;
    }>(),
    {
        placeholder: undefined,
        located: false,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
    (e: 'select', place: GeocodedPlace): void;
}>();

const { results, loading, error, search, clear } = useGeocoder();

const open = ref(false);
/** El usuario tecleó desde la última selección: hay que volver a buscar. */
const dirty = ref(false);

watch(
    () => props.modelValue,
    (value) => {
        if (!dirty.value) {
            return;
        }

        open.value = value.trim().length >= 3;
        search(value);
    },
);

function onInput(value: string): void {
    dirty.value = true;
    emit('update:modelValue', value);
}

function choose(place: GeocodedPlace): void {
    dirty.value = false;
    emit('update:modelValue', place.label);
    emit('select', place);
    open.value = false;
    clear();
}
</script>

<template>
    <div class="relative">
        <div class="relative">
            <MapPin
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
            />
            <Input
                :id="props.id"
                :model-value="props.modelValue"
                type="text"
                autocomplete="off"
                class="pl-9"
                :placeholder="
                    props.placeholder ??
                    $t('Escribe la dirección o el nombre del lugar')
                "
                @update:model-value="onInput(String($event))"
                @keydown.escape="open = false"
            />
            <Loader2
                v-if="loading"
                class="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-muted-foreground"
            />
            <Check
                v-else-if="props.located"
                class="absolute top-1/2 right-3 size-4 -translate-y-1/2 text-primary-readable"
                :aria-label="$t('Punto guardado')"
            />
        </div>

        <div
            v-if="open && (results.length > 0 || error)"
            class="absolute z-30 mt-1 w-full overflow-hidden rounded-lg border border-border bg-popover shadow-lg"
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
