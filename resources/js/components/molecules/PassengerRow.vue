<script setup lang="ts">
import { Pencil, UserPlus } from 'lucide-vue-next';
import { computed } from 'vue';
import InitialsAvatar from '@/components/atoms/InitialsAvatar.vue';
import PaymentStatusChip from '@/components/molecules/PaymentStatusChip.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { formatCurrency, formatTourDate } from '@/lib/format';
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

const documentLabel = computed(() => {
    const { document_type_label: type, document_number: number } =
        props.passenger;

    if (number === null || number === '') {
        return null;
    }

    return type === null ? number : `${type} · ${number}`;
});

const medicalNote = computed(() => {
    const note = props.passenger.medical_notes;

    return note === null || note === undefined || note.trim() === ''
        ? null
        : note;
});

const epsLabel = computed(() => {
    const { eps, eps_label: label, eps_other: other } = props.passenger;

    if (!eps) {
        return null;
    }

    return eps === 'other' && other ? other : (label ?? null);
});

const payment = computed(() => props.passenger.payment);

const emergency = computed(() => {
    const { emergency_contact_name: name, emergency_contact_phone: phone } =
        props.passenger;

    if (!name && !phone) {
        return null;
    }

    return [name, phone].filter((part) => Boolean(part)).join(' · ');
});

const rowLabel = computed(() =>
    t('Ver ficha de :name', { name: props.passenger.full_name }),
);
</script>

<template>
    <tr
        class="cursor-pointer border-b border-brand-line-2 align-top transition last:border-0 hover:bg-brand-green-50 focus-visible:bg-brand-green-50 focus-visible:outline-none"
        tabindex="0"
        :aria-label="rowLabel"
        @click="emit('select', props.passenger)"
        @keydown.enter.prevent="emit('select', props.passenger)"
        @keydown.space.prevent="emit('select', props.passenger)"
    >
        <td class="px-3 py-3">
            <div class="flex items-start gap-3">
                <InitialsAvatar
                    :name="props.passenger.full_name"
                    :pending="isPending"
                    size="sm"
                />
                <div class="min-w-0">
                    <p class="truncate font-medium text-foreground">
                        {{ props.passenger.full_name }}
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
                    <p
                        v-if="props.passenger.booking_number"
                        class="mt-0.5 truncate font-mono text-[11px] text-muted-foreground"
                    >
                        {{ props.passenger.booking_number }}
                    </p>
                </div>
            </div>
        </td>

        <td class="px-3 py-3 text-sm text-muted-foreground">
            <span v-if="documentLabel">{{ documentLabel }}</span>
            <span v-else class="text-muted-foreground/60">—</span>
        </td>

        <td class="px-3 py-3 text-sm text-muted-foreground">
            <p v-if="props.passenger.email" class="truncate">
                {{ props.passenger.email }}
            </p>
            <p v-if="props.passenger.phone" class="truncate">
                {{ props.passenger.phone }}
            </p>
            <span
                v-if="!props.passenger.email && !props.passenger.phone"
                class="text-muted-foreground/60"
            >
                —
            </span>
        </td>

        <td class="px-3 py-3 text-sm text-muted-foreground">
            <span v-if="emergency" class="line-clamp-2">{{ emergency }}</span>
            <span v-else class="text-muted-foreground/60">—</span>
        </td>

        <!--
          Columna de observaciones: solo existe con el permiso médico. Sin él no
          se dibuja el `<td>` — nada de guiones ni candados, que solo señalan lo
          que hay detrás (D7).
        -->
        <td v-if="props.canViewMedical" class="px-3 py-3 text-sm">
            <p
                v-if="medicalNote"
                class="line-clamp-2 font-semibold text-brand-drop"
            >
                {{ medicalNote }}
            </p>
            <p v-if="epsLabel" class="text-xs text-muted-foreground">
                {{ epsLabel }}
            </p>
            <span
                v-if="!medicalNote && !epsLabel"
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

        <td class="px-3 py-3 text-right">
            <PaymentStatusChip v-if="payment" :status="payment.status" />
            <p
                v-if="payment && payment.status === 'due'"
                class="mt-1 text-xs font-medium text-brand-drop tabular-nums"
            >
                {{ formatCurrency(payment.due_amount, payment.currency) }}
            </p>
        </td>

        <!--
          Una fila de marcador de posición no se edita: se COMPLETA. El botón
          abre el alta sobre esa reserva, que es lo que le falta.
        -->
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
                <UserPlus v-if="isPending" class="size-4" />
                <Pencil v-else class="size-4" />
            </Button>
        </td>
    </tr>
</template>
