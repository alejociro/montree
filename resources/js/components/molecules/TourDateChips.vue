<script setup lang="ts">
import { useTenantCurrency } from '@/composables/useTenant';
import { useTranslations } from '@/composables/useTranslations';
import { formatCurrency, formatTourDate } from '@/lib/format';
import type { TourDetailDate } from '@/types/tour-detail';

/**
 * Selector de fecha de la ficha pública (T7): fichas que envuelven en vez de
 * un `<select>` enorme, para que el viajero vea de un vistazo fecha, cupos y
 * precio de cada salida reservable. Se usa arriba en móvil y dentro de la
 * tarjeta de reserva en escritorio: las dos vistas comparten esta lista.
 */
const { t } = useTranslations();
const currency = useTenantCurrency();

const props = defineProps<{
    dates: TourDetailDate[];
    selectedDateId: number | null;
}>();

const emit = defineEmits<{
    (e: 'update:selectedDateId', value: number | null): void;
}>();

function select(id: number): void {
    emit('update:selectedDateId', props.selectedDateId === id ? null : id);
}
</script>

<template>
    <ul
        class="flex flex-wrap gap-2"
        role="listbox"
        :aria-label="t('Fecha de salida')"
    >
        <li v-for="date in props.dates" :key="date.id" class="contents">
            <button
                type="button"
                role="option"
                :aria-selected="props.selectedDateId === date.id"
                class="min-w-0 rounded-xl border px-3 py-2 text-left text-[13px] transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :class="
                    props.selectedDateId === date.id
                        ? 'border-primary bg-primary-soft text-primary-readable'
                        : 'border-border bg-card hover:border-primary/50'
                "
                @click="select(date.id)"
            >
                <span
                    class="block font-semibold text-foreground first-letter:uppercase"
                >
                    {{
                        formatTourDate(date.starts_at, {
                            withWeekday: true,
                        })
                    }}
                </span>
                <span class="mt-0.5 block text-muted-foreground">
                    {{ $tc(':count cupo|:count cupos', date.available_seats) }}
                    ·
                    {{ formatCurrency(date.effective_price, currency) }}
                </span>
            </button>
        </li>
    </ul>
</template>
