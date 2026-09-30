<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarOff, MapPinned, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import TourDateChips from '@/components/molecules/TourDateChips.vue';
import { useTenantCurrency } from '@/composables/useTenant';
import { useTranslations } from '@/composables/useTranslations';
import { formatCurrency, formatTourDate } from '@/lib/format';
import type { TourDetail, TourDetailDate } from '@/types/tour-detail';

const { t } = useTranslations();
const currency = useTenantCurrency();

const props = defineProps<{
    tour: TourDetail;
    dates: TourDetailDate[];
    selectedDateId: number | null;
    /** Índice de la parada de recogida; null cuando el tour no la define. */
    pickupStopIndex: number | null;
}>();

const emit = defineEmits<{
    (e: 'update:selectedDateId', value: number | null): void;
    (e: 'show-pickup'): void;
}>();

const selectedDate = computed<TourDetailDate | null>(
    () => props.dates.find((date) => date.id === props.selectedDateId) ?? null,
);

const price = computed(() =>
    formatCurrency(
        selectedDate.value?.effective_price ?? props.tour.from_price,
        currency.value,
    ),
);

const bookingUrl = computed(() =>
    selectedDate.value === null
        ? null
        : `/booking/new?tour_date_id=${selectedDate.value.id}`,
);

const bookingClosesLabel = computed(() =>
    selectedDate.value?.effective_booking_closes_at
        ? t('Reservas hasta el :date.', {
              date: formatTourDate(
                  selectedDate.value.effective_booking_closes_at,
                  { withTime: true },
              ),
          })
        : null,
);
</script>

<template>
    <aside class="flex min-w-0 flex-col gap-3.5 lg:sticky lg:top-[78px]">
        <div
            class="rounded-2xl border border-border bg-card p-5 shadow-[0_14px_40px_-28px_rgba(20,48,31,0.5)]"
        >
            <p class="flex items-baseline gap-2">
                <span class="text-3xl font-semibold tracking-tight">{{
                    price
                }}</span>
                <span class="text-[13px] text-muted-foreground"
                    >/ {{ $t('persona') }}</span
                >
            </p>

            <template v-if="dates.length > 0">
                <div class="mt-4 space-y-2">
                    <p
                        class="text-[11px] tracking-[0.08em] text-muted-foreground uppercase"
                    >
                        {{ $t('Fecha de salida') }}
                    </p>
                    <TourDateChips
                        :dates="dates"
                        :selected-date-id="selectedDateId"
                        @update:selected-date-id="
                            emit('update:selectedDateId', $event)
                        "
                    />
                </div>

                <p
                    v-if="selectedDate"
                    class="mt-2.5 flex items-center gap-1.5 text-[13px] text-muted-foreground"
                >
                    <Users class="size-4 text-primary-readable" />
                    {{
                        $tc(
                            ':count cupo disponible|:count cupos disponibles',
                            selectedDate.available_seats,
                        )
                    }}
                </p>
                <p
                    v-if="bookingClosesLabel"
                    class="mt-1 text-[12.5px] text-muted-foreground"
                >
                    {{ bookingClosesLabel }}
                </p>
                <p
                    v-else-if="!selectedDate"
                    class="mt-2.5 text-[13px] text-muted-foreground"
                >
                    {{
                        $t(
                            'Elige una fecha para ver el itinerario y lo que incluye.',
                        )
                    }}
                </p>

                <Link
                    v-if="bookingUrl"
                    :href="bookingUrl"
                    class="mt-3.5 block rounded-full bg-primary px-4 py-3.5 text-center text-[15px] font-semibold text-primary-foreground transition hover:bg-primary-hover"
                >
                    {{ $t('Reservar ahora') }}
                </Link>
                <button
                    v-else
                    type="button"
                    disabled
                    class="mt-3.5 w-full rounded-full bg-primary/50 px-4 py-3.5 text-[15px] font-semibold text-primary-foreground"
                >
                    {{ $t('Reservar ahora') }}
                </button>
            </template>

            <div
                v-else
                class="mt-4 rounded-xl border border-dashed border-border px-4 py-6 text-center"
            >
                <CalendarOff class="mx-auto size-8 text-muted-foreground/40" />
                <p class="mt-3 font-medium">
                    {{ $t('Sin fechas disponibles') }}
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{
                        tour.future_dates.length === 0
                            ? $t(
                                  'Todavía no hay salidas programadas para esta experiencia. Vuelve pronto para reservar.',
                              )
                            : $t(
                                  'Por ahora no quedan cupos abiertos. Vuelve pronto para nuevas salidas.',
                              )
                    }}
                </p>
            </div>

            <button
                v-if="pickupStopIndex !== null"
                type="button"
                class="mt-2 flex w-full items-center justify-center gap-2 rounded-full border border-border px-4 py-3 text-sm font-semibold text-primary-readable transition hover:bg-primary-soft"
                @click="emit('show-pickup')"
            >
                <MapPinned class="size-4" />
                {{ $t('Ver punto de recogida en el mapa') }}
            </button>
        </div>

        <slot name="logistics" />
    </aside>
</template>
