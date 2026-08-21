<script setup lang="ts">
import {
    AlertTriangle,
    Download,
    Printer,
    RefreshCw,
    UsersRound,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import PassengerDrawer from '@/components/molecules/PassengerDrawer.vue';
import PassengerFilters from '@/components/molecules/PassengerFilters.vue';
import PassengerFormDialog from '@/components/molecules/PassengerFormDialog.vue';
import PassengerRow from '@/components/molecules/PassengerRow.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { usePassengerManifest } from '@/composables/usePassengerManifest';
import type { PassengerManifestSource } from '@/composables/usePassengerManifest';
import { usePermissions } from '@/composables/usePermissions';
import { formatCurrency, formatTourDate } from '@/lib/format';
import type { Passenger } from '@/types/passenger';

type Props = {
    source: PassengerManifestSource;
    /** Zona del guía: ninguna acción de escritura se dibuja. */
    readonly?: boolean;
    /** Título del encabezado; el guía usa el nombre de su salida. */
    title?: string;
};

const props = withDefaults(defineProps<Props>(), {
    readonly: false,
    title: '',
});

const { can } = usePermissions();

const {
    passengers,
    loading,
    error,
    segment,
    search,
    tourDateId,
    page,
    canViewMedical,
    summary,
    departures,
    lastPage,
    total,
    printRows,
    exportUrl,
    reload,
} = usePassengerManifest(props.source);

const canEdit = computed(() => !props.readonly && can('bookings.update'));

const isTourScope = computed(() => props.source.kind === 'tour');

/** En «Todas las salidas» la fecha de cada fila es la única forma de ubicarla. */
const showDeparture = computed(
    () => isTourScope.value && tourDateId.value === null,
);

const columnCount = computed(
    () =>
        4 +
        (canViewMedical.value ? 1 : 0) +
        (showDeparture.value ? 1 : 0) +
        1 +
        (props.readonly ? 0 : 1),
);

const drawerOpen = ref(false);
const selected = ref<Passenger | null>(null);

const formOpen = ref(false);
const editing = ref<Passenger | null>(null);

function openDrawer(passenger: Passenger): void {
    selected.value = passenger;
    drawerOpen.value = true;
}

function openEdit(passenger: Passenger): void {
    editing.value = passenger;
    formOpen.value = true;
    drawerOpen.value = false;
}

function onSaved(): void {
    void reload();
}

function onPaymentRegistered(): void {
    drawerOpen.value = false;
    void reload();
}

function print(): void {
    window.print();
}
</script>

<template>
    <section class="space-y-4">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-foreground">
                    {{ props.title || $t('Pasajeros') }}
                </h2>
                <p class="mt-0.5 text-sm text-muted-foreground">
                    {{
                        $t(
                            'Reservas confirmadas y completadas de esta operación.',
                        )
                    }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2 print:hidden">
                <Button variant="outline" size="sm" @click="print">
                    <Printer class="size-4" />
                    {{ $t('Imprimir') }}
                </Button>
                <a :href="exportUrl" download>
                    <Button variant="outline" size="sm">
                        <Download class="size-4" />
                        {{ $t('Exportar CSV') }}
                    </Button>
                </a>
            </div>
        </header>

        <div class="print:hidden">
            <PassengerFilters
                :segment="segment"
                :search="search"
                :departures="departures"
                :tour-date-id="tourDateId"
                :can-view-medical="canViewMedical"
                :show-departure-select="isTourScope"
                @update:segment="segment = $event"
                @update:search="search = $event"
                @update:tour-date-id="tourDateId = $event"
            />
        </div>

        <!-- Error -->
        <div
            v-if="error"
            class="flex flex-col items-center gap-3 rounded-xl border border-brand-drop/30 bg-brand-drop-50 p-8 text-center print:hidden"
        >
            <AlertTriangle class="size-8 text-brand-drop" />
            <p class="text-sm font-medium text-brand-drop">{{ error }}</p>
            <Button variant="outline" size="sm" @click="reload">
                <RefreshCw class="size-4" />
                {{ $t('Reintentar') }}
            </Button>
        </div>

        <!-- Loading: esqueleto de filas, no un spinner suelto -->
        <div
            v-else-if="loading"
            class="space-y-2 rounded-xl border border-border bg-card p-4 print:hidden"
        >
            <Skeleton
                v-for="row in 6"
                :key="row"
                class="h-12 w-full rounded-md"
            />
        </div>

        <!-- Vacío -->
        <div
            v-else-if="passengers.length === 0"
            class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-border p-10 text-center print:hidden"
        >
            <UsersRound class="size-8 text-muted-foreground/40" />
            <p class="font-medium text-foreground">
                {{ $t('Ningún pasajero coincide con el filtro.') }}
            </p>
            <p class="max-w-sm text-sm text-muted-foreground">
                {{
                    $t(
                        'Prueba con «Todos», limpia la búsqueda o elige otra salida.',
                    )
                }}
            </p>
        </div>

        <!-- Tabla -->
        <div
            v-else
            class="overflow-hidden rounded-xl border border-border bg-card print:hidden"
        >
            <div class="max-h-[70vh] overflow-auto">
                <table class="w-full border-collapse text-left">
                    <thead
                        class="sticky top-0 z-10 bg-card shadow-[0_1px_0_var(--brand-line-2)]"
                    >
                        <tr>
                            <th class="px-3 py-2.5">
                                <MonoLabel as="span">
                                    {{ $t('Pasajero') }}
                                </MonoLabel>
                            </th>
                            <th class="px-3 py-2.5">
                                <MonoLabel as="span">
                                    {{ $t('Documento') }}
                                </MonoLabel>
                            </th>
                            <th class="px-3 py-2.5">
                                <MonoLabel as="span">
                                    {{ $t('Contacto') }}
                                </MonoLabel>
                            </th>
                            <th class="px-3 py-2.5">
                                <MonoLabel as="span">
                                    {{ $t('Emergencia') }}
                                </MonoLabel>
                            </th>
                            <th v-if="canViewMedical" class="px-3 py-2.5">
                                <MonoLabel as="span">
                                    {{ $t('Observaciones') }}
                                </MonoLabel>
                            </th>
                            <th v-if="showDeparture" class="px-3 py-2.5">
                                <MonoLabel as="span">
                                    {{ $t('Salida') }}
                                </MonoLabel>
                            </th>
                            <th class="px-3 py-2.5 text-right">
                                <MonoLabel as="span">
                                    {{ $t('Pago') }}
                                </MonoLabel>
                            </th>
                            <th v-if="!props.readonly" class="px-3 py-2.5">
                                <span class="sr-only">
                                    {{ $t('Acciones') }}
                                </span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <PassengerRow
                            v-for="(passenger, index) in passengers"
                            :key="
                                passenger.id ??
                                `pending-${passenger.booking_number}-${index}`
                            "
                            :passenger="passenger"
                            :can-view-medical="canViewMedical"
                            :readonly="props.readonly"
                            :show-departure="showDeparture"
                            :can-edit="canEdit"
                            @select="openDrawer"
                            @edit="openEdit"
                        />
                    </tbody>
                    <tfoot
                        v-if="summary"
                        class="border-t border-border bg-muted/40"
                    >
                        <tr>
                            <td :colspan="columnCount" class="px-3 py-3">
                                <div
                                    class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground"
                                >
                                    <span class="font-medium text-foreground">
                                        {{
                                            $t(':count pasajeros', {
                                                count: summary.total_passengers,
                                            })
                                        }}
                                    </span>
                                    <span>
                                        {{
                                            $t(':count con saldo pendiente', {
                                                count: summary.with_due,
                                            })
                                        }}
                                    </span>
                                    <!--
                                      `with_notes` solo llega con el permiso
                                      médico: el conteo agregado también dice
                                      cuántos hay (D7).
                                    -->
                                    <span
                                        v-if="summary.with_notes !== undefined"
                                        class="font-medium text-brand-drop"
                                    >
                                        {{
                                            $t(':count con observaciones', {
                                                count: summary.with_notes,
                                            })
                                        }}
                                    </span>
                                    <span
                                        class="font-semibold text-foreground tabular-nums"
                                    >
                                        {{
                                            $t('Total por cobrar :amount', {
                                                amount: formatCurrency(
                                                    summary.total_due_amount,
                                                    summary.currency,
                                                ),
                                            })
                                        }}
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div
            v-if="!loading && !error && lastPage > 1"
            class="flex items-center justify-between gap-3 print:hidden"
        >
            <p class="text-sm text-muted-foreground">
                {{
                    $t('Página :page de :last · :total pasajeros', {
                        page,
                        last: lastPage,
                        total,
                    })
                }}
            </p>
            <div class="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="page <= 1"
                    @click="page = page - 1"
                >
                    {{ $t('Anterior') }}
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="page >= lastPage"
                    @click="page = page + 1"
                >
                    {{ $t('Siguiente') }}
                </Button>
            </div>
        </div>

        <!--
          Hoja de impresión. Es un bloque aparte, no la misma tabla, por una
          razón concreta: imprime `printRows` —TODO el resultado filtrado— y no
          las 50 filas de la página visible. El guía se lleva al vehículo la
          planilla completa o no se lleva nada.
        -->
        <div data-passenger-print class="hidden print:block" aria-hidden="true">
            <h1 class="mb-1 text-lg font-bold">
                {{ props.title || $t('Pasajeros') }}
            </h1>
            <p v-if="summary" class="mb-3 text-xs">
                {{
                    $t(':count pasajeros', {
                        count: summary.total_passengers,
                    })
                }}
                ·
                {{
                    $t(':count con saldo pendiente', {
                        count: summary.with_due,
                    })
                }}
                ·
                {{
                    $t('Total por cobrar :amount', {
                        amount: formatCurrency(
                            summary.total_due_amount,
                            summary.currency,
                        ),
                    })
                }}
            </p>
            <table class="w-full border-collapse text-[10px]">
                <thead>
                    <tr>
                        <th class="border-b py-1 text-left">
                            {{ $t('Pasajero') }}
                        </th>
                        <th class="border-b py-1 text-left">
                            {{ $t('Documento') }}
                        </th>
                        <th class="border-b py-1 text-left">
                            {{ $t('Contacto') }}
                        </th>
                        <th class="border-b py-1 text-left">
                            {{ $t('Emergencia') }}
                        </th>
                        <th
                            v-if="canViewMedical"
                            class="border-b py-1 text-left"
                        >
                            {{ $t('Observaciones') }}
                        </th>
                        <th class="border-b py-1 text-left">
                            {{ $t('Salida') }}
                        </th>
                        <th class="border-b py-1 text-right">
                            {{ $t('Saldo') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(row, index) in printRows"
                        :key="`print-${row.id ?? index}`"
                    >
                        <td class="border-b py-1 align-top">
                            {{ row.full_name }}
                            <template v-if="row.id === null">
                                ({{ $t('Datos pendientes') }})
                            </template>
                        </td>
                        <td class="border-b py-1 align-top">
                            {{ row.document_number ?? '' }}
                        </td>
                        <td class="border-b py-1 align-top">
                            {{ row.phone ?? row.email ?? '' }}
                        </td>
                        <td class="border-b py-1 align-top">
                            {{ row.emergency_contact_name ?? '' }}
                            {{ row.emergency_contact_phone ?? '' }}
                        </td>
                        <td
                            v-if="canViewMedical"
                            class="border-b py-1 align-top font-semibold"
                        >
                            {{ row.medical_notes ?? '' }}
                        </td>
                        <td class="border-b py-1 align-top">
                            <template v-if="row.departure_starts_at">
                                {{
                                    formatTourDate(row.departure_starts_at, {
                                        withWeekday: false,
                                        withTime: false,
                                    })
                                }}
                            </template>
                        </td>
                        <td class="border-b py-1 text-right align-top">
                            <template v-if="row.payment">
                                {{
                                    formatCurrency(
                                        row.payment.due_amount,
                                        row.payment.currency,
                                    )
                                }}
                            </template>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <PassengerDrawer
            :open="drawerOpen"
            :passenger="selected"
            :can-view-medical="canViewMedical"
            :readonly="props.readonly"
            :can-edit="canEdit"
            @update:open="drawerOpen = $event"
            @edit="openEdit"
            @payment-registered="onPaymentRegistered"
        />

        <PassengerFormDialog
            v-if="canEdit"
            :open="formOpen"
            :passenger="editing"
            :booking-number="editing?.booking_number ?? null"
            :can-view-medical="canViewMedical"
            @update:open="formOpen = $event"
            @saved="onSaved"
        />
    </section>
</template>

<style>
/*
  WHY sin `scoped`: la hoja de impresión tiene que apagar la barra lateral, el
  encabezado y el resto de la página, que viven fuera de este componente. Con
  `visibility` en vez de `display` no hace falta enumerarlos uno por uno: si
  mañana aparece otro chrome, sigue funcionando.
*/
@media print {
    body * {
        visibility: hidden;
    }

    [data-passenger-print],
    [data-passenger-print] * {
        visibility: visible;
    }

    [data-passenger-print] {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        overflow: visible !important;
    }

    @page {
        size: landscape;
        margin: 12mm;
    }
}
</style>
