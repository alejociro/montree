<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

/**
 * Listado corto editable —incluye / no incluye / requisitos— como fichas.
 *
 * WHY: antes eran tres `<textarea>` de «una entrada por línea», y una lista que
 * el catálogo público pinta como ítems se editaba a ciegas. Con fichas, cada
 * entrada se ve, se quita y se cuenta contra el máximo del Form Request
 * (`max:30` por lista, `max:200` por ítem) antes de mandar nada al servidor.
 */
type Props = {
    id: string;
    label: string;
    modelValue: string[];
    placeholder?: string;
    hint?: string;
    error?: string;
    /** Tope del Form Request: `includes|excludes|requirements` → `max:30`. */
    max?: number;
    /** Tope por ítem: `*.*` → `max:200`. */
    maxLength?: number;
};

const props = withDefaults(defineProps<Props>(), {
    placeholder: undefined,
    hint: undefined,
    error: undefined,
    max: 30,
    maxLength: 200,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: string[]): void;
}>();

const draft = ref('');

const full = computed<boolean>(() => props.modelValue.length >= props.max);

const placeholderText = computed<string>(() =>
    full.value
        ? t('Máximo :max entradas', { max: props.max })
        : (props.placeholder ?? t('Agregar…')),
);

function commit(): void {
    const value = draft.value.trim().slice(0, props.maxLength);

    if (value === '') {
        draft.value = '';

        return;
    }

    // WHY: repetir «Guía certificado» en la misma lista no aporta nada al
    // viajero y el catálogo lo pintaría dos veces.
    if (full.value || props.modelValue.includes(value)) {
        draft.value = '';

        return;
    }

    emit('update:modelValue', [...props.modelValue, value]);
    draft.value = '';
}

function removeAt(index: number): void {
    emit(
        'update:modelValue',
        props.modelValue.filter((_, position) => position !== index),
    );
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        commit();

        return;
    }

    // WHY: retroceso con el campo vacío quita la última ficha, como en cualquier
    // campo de etiquetas. Sin esto hay que apuntarle a la «×» con el ratón.
    if (
        event.key === 'Backspace' &&
        draft.value === '' &&
        props.modelValue.length > 0
    ) {
        removeAt(props.modelValue.length - 1);
    }
}
</script>

<template>
    <div class="grid gap-2">
        <Label :for="props.id">{{ props.label }}</Label>

        <div
            class="flex min-h-11 flex-wrap items-center gap-1.5 rounded-lg border border-input bg-card p-2 focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/40"
        >
            <ul v-if="props.modelValue.length > 0" class="contents">
                <li
                    v-for="(item, index) in props.modelValue"
                    :key="`${item}-${index}`"
                    class="inline-flex items-center gap-1.5 rounded-full border border-secondary bg-brand-green-50 py-1 pr-1.5 pl-3 text-xs"
                >
                    <span>{{ item }}</span>
                    <button
                        type="button"
                        class="rounded-full p-0.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :aria-label="$t('Quitar :item', { item })"
                        @click="removeAt(index)"
                    >
                        <X class="size-3" />
                    </button>
                </li>
            </ul>

            <input
                :id="props.id"
                v-model="draft"
                type="text"
                :maxlength="props.maxLength"
                :disabled="full"
                :placeholder="placeholderText"
                class="min-w-32 flex-1 bg-transparent px-1.5 py-1 text-sm outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed"
                :aria-describedby="`${props.id}-hint`"
                @keydown="onKeydown"
                @blur="commit"
            />
        </div>

        <p :id="`${props.id}-hint`" class="text-xs text-muted-foreground">
            {{
                props.hint ??
                $t('Escribe y presiona Enter. :count de :max', {
                    count: props.modelValue.length,
                    max: props.max,
                })
            }}
        </p>

        <InputError :message="props.error" />
    </div>
</template>
