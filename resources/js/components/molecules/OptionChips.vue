<script setup lang="ts">
import { computed } from 'vue';
import type { SelectOption } from '@/lib/logistics';
import { cn } from '@/lib/utils';

/**
 * Grupo de fichas para elegir una opción o varias.
 *
 * WHY: el sistema de diseño usa fichas —no un `<select>`— cuando las opciones
 * caben en pantalla y conviene verlas todas de un vistazo: el tipo de servicio
 * de un proveedor o lo que incluye un hotel se decide comparando, y un select
 * las esconde de a una.
 */
type Props = {
    modelValue: string | string[];
    options: SelectOption[];
    multiple?: boolean;
    label: string;
};

const props = withDefaults(defineProps<Props>(), { multiple: false });

const emit = defineEmits<{
    (e: 'update:modelValue', value: string | string[]): void;
}>();

const selected = computed<string[]>(() =>
    Array.isArray(props.modelValue)
        ? props.modelValue
        : props.modelValue === ''
          ? []
          : [props.modelValue],
);

function isOn(value: string): boolean {
    return selected.value.includes(value);
}

function toggle(value: string): void {
    if (!props.multiple) {
        // Volver a pulsar la ficha activa la suelta: sin esto, un campo
        // opcional que se marcó por error no se puede dejar vacío.
        emit('update:modelValue', isOn(value) ? '' : value);

        return;
    }

    emit(
        'update:modelValue',
        isOn(value)
            ? selected.value.filter((item) => item !== value)
            : [...selected.value, value],
    );
}
</script>

<template>
    <div
        class="flex flex-wrap gap-2"
        :role="props.multiple ? 'group' : 'radiogroup'"
        :aria-label="props.label"
    >
        <button
            v-for="option in props.options"
            :key="option.value"
            type="button"
            :aria-pressed="props.multiple ? isOn(option.value) : undefined"
            :role="props.multiple ? undefined : 'radio'"
            :aria-checked="props.multiple ? undefined : isOn(option.value)"
            :class="
                cn(
                    'rounded-full border px-3.5 py-1.5 text-[13px] transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                    isOn(option.value)
                        ? 'border-secondary bg-secondary text-secondary-foreground'
                        : 'border-input bg-card text-foreground hover:border-secondary/60 hover:bg-secondary-soft',
                )
            "
            @click="toggle(option.value)"
        >
            {{ option.label }}
        </button>
    </div>
</template>
