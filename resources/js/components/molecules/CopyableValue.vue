<script setup lang="ts">
import { Check, Copy } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';
import { toast } from 'vue-sonner';
import { useTranslations } from '@/composables/useTranslations';
import { cn } from '@/lib/utils';

const { t } = useTranslations();

type Props = {
    label: string;
    value: string | null;
    /** Texto para el hueco: un pago manual no tiene campos de pasarela. */
    emptyText?: string;
};

const props = withDefaults(defineProps<Props>(), { emptyText: '' });

const copied = ref(false);
let resetTimer: ReturnType<typeof setTimeout> | null = null;

const placeholder = computed(() => props.emptyText || t('Sin dato'));

const hasValue = computed(
    () => props.value !== null && props.value.trim() !== '',
);

/**
 * WHY: `navigator.clipboard` no existe fuera de un contexto seguro (http en una
 * IP, un subdominio local sin TLS), así que el camino moderno se intenta y, si
 * no está o falla, se cae al textarea + `execCommand`. Copiar un identificador
 * nunca puede romper la pantalla desde la que se copia.
 */
async function writeToClipboard(value: string): Promise<boolean> {
    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(value);

            return true;
        }
    } catch {
        // Cae al fallback de abajo.
    }

    return legacyCopy(value);
}

function legacyCopy(value: string): boolean {
    try {
        const area = document.createElement('textarea');
        area.value = value;
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        const copiedByCommand = document.execCommand('copy');
        document.body.removeChild(area);

        return copiedByCommand;
    } catch {
        return false;
    }
}

async function copy(): Promise<void> {
    if (!hasValue.value || props.value === null) {
        return;
    }

    const done = await writeToClipboard(props.value);

    if (!done) {
        toast.error(t('No pudimos copiar. Seleccioná el texto a mano.'));

        return;
    }

    copied.value = true;
    toast.success(t(':label copiado.', { label: props.label }));

    if (resetTimer) {
        clearTimeout(resetTimer);
    }

    resetTimer = setTimeout(() => {
        copied.value = false;
    }, 2000);
}

onBeforeUnmount(() => {
    if (resetTimer) {
        clearTimeout(resetTimer);
    }
});
</script>

<template>
    <div class="flex items-start justify-between gap-3">
        <dt class="text-sm text-muted-foreground">{{ props.label }}</dt>
        <dd class="flex min-w-0 items-center gap-1.5">
            <span
                :class="
                    cn(
                        'truncate font-mono text-sm',
                        hasValue ? 'text-foreground' : 'text-muted-foreground',
                    )
                "
            >
                {{ hasValue ? props.value : placeholder }}
            </span>
            <button
                v-if="hasValue"
                type="button"
                class="rounded-md p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :aria-label="$t('Copiar :label', { label: props.label })"
                @click="copy"
            >
                <Check v-if="copied" class="size-4 text-brand-green-600" />
                <Copy v-else class="size-4" />
            </button>
        </dd>
    </div>
</template>
