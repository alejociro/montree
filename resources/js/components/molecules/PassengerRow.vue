<script setup lang="ts">
import { Pencil, TriangleAlert } from 'lucide-vue-next';
import { computed } from 'vue';
import InitialsAvatar from '@/components/atoms/InitialsAvatar.vue';
import PaymentStatusChip from '@/components/molecules/PaymentStatusChip.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { formatTourDate } from '@/lib/format';
import type { Passenger } from '@/types/passenger';

const { t } = useTranslations();

type Props = {
    passenger: Passenger;
    /** Viene de `meta.can_view_medical`; el front no reinventa el chequeo. */
    canViewMedical: boolean;
    /** Zona del guía: solo lee. */
    readonly: boolean;
    /** Panel con varias salidas en pantalla. */
    showDeparture: boolean;
    /** `bookings.update`: sin él tampoco se ofrece editar. */
    canEdit: boolean;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    select: [passenger: Passenger];
    edit: [passenger: Passenger];
}>();

/**
 * Reserva sin viajeros cargados. No se esconde: un guía que no vea a esa
 * persona en la lista la deja fuera del vehículo.
 */
const isPending = computed(() => props.passenger.id === null);

/**
 * Tipo ABREVIADO y número en la misma celda. El tipo escrito completo
 * —«Cédula de ciudadanía»— ocupaba tres líneas y empujaba al número, que es
 * con lo que el guía identifica a la persona en la puerta del vehículo.
 */
const documentLabel = computed(() => {
    const {
        document_type_abbreviation: abbreviation,
        document_number: number,
    } = props.passenger;

    if (number === null || number === '') {
        return null;
    }

    return abbreviation === null ? number : `${abbreviation} · ${number}`;
});

/** Etiqueta larga para el `title`: la abreviatura sola no se explica. */
const documentTitle = computed(() => {
    const { document_type_label: label, document_number: number } =
        props.passenger;

    return label === null || number === null ? null : `${label} · ${number}`;
});

/**
 * La observación médica ya no tiene columna propia —saturaba la vista—, pero
 * tampoco puede desaparecer de la pantalla: quien la puede ver necesita saber
 * de un vistazo que esa persona la tiene. Queda como marca junto al nombre y
 * el texto completo vive en la ficha.
 */
const hasMedicalNote = computed(() => {
    const note = props.passenger.medical_notes;

    return (
        props.canViewMedical &&
        note !== null &&
        note !== undefined &&
        note.trim() !== ''
    );
});

const payment = computed(() => props.passenger.payment);

const rowLabel = computed(() =>
    t('Ver ficha de :name', { name: props.passenger.full_name }),
);
</script>

<template>
    <tr
        class="cursor-pointer border-b border-brand-line-2 align-middle transition last:border-0 hover:bg-primary-soft focus-visible:bg-primary-soft focus-visible:outline-none"
        tabindex="0"
        :aria-label="rowLabel"
        @click="emit('select', props.passenger)"
        @keydown.enter.prevent="emit('select', props.passenger)"
        @keydown.space.prevent="emit('select', props.passenger)"
    >
        <td class="px-3 py-3">
            <div class="flex items-center gap-3">
                <InitialsAvatar
                    :name="props.passenger.full_name"
                    :pending="isPending"
                    size="sm"
                />
                <div class="min-w-0">
                    <p
                        class="flex items-center gap-1.5 truncate font-medium text-foreground"
                    >
                        {{ props.passenger.full_name }}
                        <TriangleAlert
                            v-if="hasMedicalNote"
                            class="size-3.5 shrink-0 text-brand-drop"
                            :aria-label="$t('Tiene observaciones médicas')"
                        />
                    </p>
                    <p
                        v-if="isPending"
                        class="mt-0.5 text-xs font-medium text-brand-warn"
                    >
                        {{ $t('Datos pendientes') }}
                    </p>
                    <p
                        v-else-if="props.passenger.is_minor"
                        class="mt-0.5 text-xs text-muted-foreground"
                    >
                        {{ $t('Menor de edad') }}
                    </p>
                </div>
            </div>
        </td>

        <td class="px-3 py-3 text-sm whitespace-nowrap text-muted-foreground">
            <span v-if="documentLabel" :title="documentTitle ?? undefined">
                {{ documentLabel }}
            </span>
            <span v-else class="text-muted-foreground/60">—</span>
        </td>

        <!--
          El correo se recorta con puntos suspensivos y lleva el valor entero en
          el `title`: uno largo estiraba la columna y empujaba el resto de la
          tabla fuera de la pantalla.
        -->
        <td class="px-3 py-3 text-sm text-muted-foreground">
            <p
                v-if="props.passenger.email"
                class="max-w-[22ch] truncate"
                :title="props.passenger.email"
            >
                {{ props.passenger.email }}
            </p>
            <p v-if="props.passenger.phone" class="whitespace-nowrap">
                {{ props.passenger.phone }}
            </p>
            <span
                v-if="!props.passenger.email && !props.passenger.phone"
                class="text-muted-foreground/60"
            >
                —
            </span>
        </td>

        <td
            v-if="props.showDeparture"
            class="px-3 py-3 text-sm whitespace-nowrap text-muted-foreground"
        >
            <span v-if="props.passenger.departure_starts_at">
                {{
                    formatTourDate(props.passenger.departure_starts_at, {
                        withWeekday: false,
                        withTime: false,
                    })
                }}
            </span>
            <span v-else class="text-muted-foreground/60">—</span>
        </td>

        <!--
          Estado, sin importe. Cuánto debe cada persona es una cifra que se
          consulta, no que se barre con la vista: vive en la ficha y en el
          total del pie.
        -->
        <td class="px-3 py-3 text-right">
            <PaymentStatusChip v-if="payment" :status="payment.status" short />
        </td>

        <td v-if="!props.readonly" class="px-3 py-3 text-right">
            <Button
                v-if="props.canEdit"
                variant="ghost"
                size="sm"
                :aria-label="
                    isPending
                        ? $t('Completar los datos de :name', {
                              name: props.passenger.full_name,
                          })
                        : $t('Editar a :name', {
                              name: props.passenger.full_name,
                          })
                "
                @click.stop="emit('edit', props.passenger)"
            >
                <Pencil class="size-4" />
            </Button>
        </td>
    </tr>
</template>
