<script setup lang="ts">
import { computed, toRef } from 'vue';
import { Label } from '@/components/ui/label';
import { useGuideAvailability } from '@/composables/useGuideAvailability';
import type { DepartureRange } from '@/types/guide-availability';
import type { LogisticsRef } from '@/types/logistics';

type Props = {
    modelValue: number | null;
    /** Los días que ocuparía esta salida; `null` mientras no haya fecha. */
    range: DepartureRange | null;
    /** La salida que se edita, que no cuenta como su propio conflicto. */
    excludeTourDateId?: number | null;
    /** Se ofrece mientras la agenda no responde; el servidor sigue validando. */
    fallbackGuides?: LogisticsRef[];
    error?: string;
    id?: string;
};

const props = withDefaults(defineProps<Props>(), {
    excludeTourDateId: null,
    fallbackGuides: () => [],
    error: undefined,
    id: 'departure-guide',
});

const emit = defineEmits<{
    'update:modelValue': [value: number | null];
}>();

const { guides, loading, reasonFor } = useGuideAvailability(
    toRef(props, 'range'),
    toRef(props, 'excludeTourDateId'),
);

type Option = { id: number; name: string; reason: string | null };

const options = computed<Option[]>(() => {
    if (guides.value.length === 0) {
        return props.fallbackGuides.map((guide) => ({
            id: guide.id,
            name: guide.name,
            reason: null,
        }));
    }

    return guides.value.map((guide) => ({
        id: guide.id,
        name: guide.name,
        reason: reasonFor(guide.id),
    }));
});

/**
 * WHY (D9): el guía ocupado se deshabilita CON el motivo. Ofrecerlo y devolver
 * un 422 después es hacerle perder el formulario entero a quien programa.
 */
const busySelected = computed(() =>
    props.modelValue === null ? null : reasonFor(props.modelValue),
);

function onChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;

    emit('update:modelValue', value === '' ? null : Number(value));
}
</script>

<template>
    <div class="space-y-1.5">
        <Label :for="props.id">{{ $t('Guía *') }}</Label>
        <select
            :id="props.id"
            :value="props.modelValue ?? ''"
            class="flex h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm"
            @change="onChange"
        >
            <option value="" disabled>
                {{ $t('Elige un guía') }}
            </option>
            <option
                v-for="option in options"
                :key="option.id"
                :value="option.id"
                :disabled="option.reason !== null"
            >
                {{
                    option.reason === null
                        ? option.name
                        : `${option.name} — ${option.reason}`
                }}
            </option>
        </select>

        <p v-if="loading" class="text-xs text-muted-foreground">
            {{ $t('Consultando la agenda de los guías…') }}
        </p>
        <p
            v-else-if="props.range === null"
            class="text-xs text-muted-foreground"
        >
            {{ $t('Elige la fecha de inicio para ver quién está libre.') }}
        </p>
        <p v-else-if="busySelected" class="text-xs text-brand-warn">
            {{ busySelected }}
        </p>

        <p v-if="props.error" class="text-xs text-destructive">
            {{ props.error }}
        </p>
    </div>
</template>
