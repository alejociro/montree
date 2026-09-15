<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { computed, nextTick, reactive, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/composables/useTranslations';
import type {
    PaymentGateway,
    PaymentStatus,
    TransactionSearchField,
} from '@/types/enums.generated';
import {
    PAYMENT_GATEWAY_VALUES,
    PAYMENT_STATUS_VALUES,
    TRANSACTION_SEARCH_FIELD_VALUES,
} from '@/types/enums.generated';
import type {
    DepartureOption,
    TransactionFilterValues,
    TransactionOption,
} from '@/types/transaction';

/**
 * Filtros del listado de transacciones.
 *
 * WHY el selector en vez de un input que busque en todas las columnas: sobre
 * pagos se compara por igualdad contra un identificador que se pega entero, y
 * eso usa los índices. Un `LIKE %…%` sobre media tabla no.
 */

const { t } = useTranslations();

const page = usePage();

type Props = {
    filters: TransactionFilterValues;
    statuses: TransactionOption[];
    gateways: TransactionOption[];
    searchFields: TransactionOption[];
    departures: DepartureOption[];
    defaultDays: number;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    apply: [filters: TransactionFilterValues];
}>();

/** Centinela del `Select`: reka-ui no admite `''` como valor de un item. */
const ALL = 'all';

/**
 * Campo preseleccionado. `search` sin `search_by` es un error de validación,
 * así que el selector nunca puede estar vacío.
 */
const DEFAULT_SEARCH_FIELD: TransactionSearchField = 'reference';

/** El único campo que compara parcial y, por eso, sí respeta el rango. */
const PARTIAL_SEARCH_FIELD: TransactionSearchField = 'payer';

type LocalFilters = {
    searchBy: TransactionSearchField;
    search: string;
    status: string;
    gateway: string;
    tourDateId: string;
    from: string;
    to: string;
};

function toLocal(filters: TransactionFilterValues): LocalFilters {
    return {
        searchBy: filters.search_by ?? DEFAULT_SEARCH_FIELD,
        search: filters.search ?? '',
        status: filters.status ?? ALL,
        gateway: filters.gateway ?? ALL,
        tourDateId:
            filters.tour_date_id === null ? ALL : String(filters.tour_date_id),
        from: filters.from ?? '',
        to: filters.to ?? '',
    };
}

const local = reactive<LocalFilters>(toLocal(props.filters));

/** Evita que repintar con lo que devolvió el servidor dispare otra visita. */
let syncing = false;

// El servidor manda: tras una recarga o un «atrás» del navegador los controles
// se repintan con lo que realmente se aplicó, no con lo que quedó tecleado.
watch(
    () => props.filters,
    (filters) => {
        syncing = true;
        Object.assign(local, toLocal(filters));
        void nextTick(() => {
            syncing = false;
        });
    },
    { deep: true },
);

/**
 * Un identificador exacto busca en toda la historia: el servidor descarta el
 * rango y lo devuelve en `null`, así que los controles de fecha se apagan.
 */
const ignoresDateRange = computed(
    () => local.search.trim() !== '' && local.searchBy !== PARTIAL_SEARCH_FIELD,
);

const searchFieldLabel = computed(
    () =>
        props.searchFields.find((option) => option.value === local.searchBy)
            ?.label ?? local.searchBy,
);

/**
 * El servidor rellena `from` solo cuando la URL no trae rango. Se mira la URL
 * en vez de recalcular la fecha en el cliente: el «hoy» del navegador y el del
 * servidor pueden diferir por zona horaria y el cartel quedaría mintiendo.
 */
const explicitRange = computed(() => {
    const query = new URLSearchParams(page.url.split('?')[1] ?? '');

    return query.has('from') || query.has('to');
});

const showsDefaultWindow = computed(
    () => !ignoresDateRange.value && !explicitRange.value && local.from !== '',
);

/**
 * Los campos de fecha se deshabilitan al buscar por identificador. Sin este
 * enlace el lector de pantalla anuncia «deshabilitado» y no el motivo.
 */
const rangeHintId = computed(() =>
    ignoresDateRange.value || showsDefaultWindow.value
        ? 'transaction-range-hint'
        : undefined,
);

const hasActiveFilters = computed(
    () =>
        local.search.trim() !== '' ||
        local.status !== ALL ||
        local.gateway !== ALL ||
        local.tourDateId !== ALL ||
        (!showsDefaultWindow.value && (local.from !== '' || local.to !== '')),
);

const invalidRange = computed(
    () => local.from !== '' && local.to !== '' && local.to < local.from,
);

/**
 * Se valida contra los enums generados en vez de forzar el tipo: si el backend
 * agrega un valor y el front no se regenera, el filtro cae a «todos» en lugar
 * de mandar algo que el Form Request va a rechazar.
 */
function toStatus(value: string): PaymentStatus | null {
    return PAYMENT_STATUS_VALUES.find((status) => status === value) ?? null;
}

function toGateway(value: string): PaymentGateway | null {
    return PAYMENT_GATEWAY_VALUES.find((gateway) => gateway === value) ?? null;
}

function toSearchField(value: string): TransactionSearchField {
    return (
        TRANSACTION_SEARCH_FIELD_VALUES.find((field) => field === value) ??
        DEFAULT_SEARCH_FIELD
    );
}

function toTourDateId(value: string): number | null {
    const id = Number(value);

    return value === ALL || Number.isNaN(id) ? null : id;
}

function current(): TransactionFilterValues {
    const search = local.search.trim();
    const searching = search !== '';

    return {
        search_by: searching ? local.searchBy : null,
        search: searching ? search : null,
        status: toStatus(local.status),
        gateway: toGateway(local.gateway),
        tour_date_id: toTourDateId(local.tourDateId),
        from: ignoresDateRange.value || local.from === '' ? null : local.from,
        to: ignoresDateRange.value || local.to === '' ? null : local.to,
    };
}

/**
 * La búsqueda se aplica al enviar, no por tecla: el valor es un identificador
 * que se pega entero y una comparación exacta no devuelve nada hasta la última
 * letra.
 */
function apply(): void {
    if (invalidRange.value || syncing) {
        return;
    }

    emit('apply', current());
}

watch(
    [
        () => local.status,
        () => local.gateway,
        () => local.tourDateId,
        () => local.from,
        () => local.to,
    ],
    () => apply(),
);

/**
 * `reka-ui` emite `AcceptableValue` (string | number | Record | null), así que
 * el valor se estrecha acá en vez de forzar el tipo con un `as`.
 */
function selected(value: unknown): string {
    return typeof value === 'string' ? value : ALL;
}

function onSelect(
    field: 'status' | 'gateway' | 'tourDateId',
    value: unknown,
): void {
    local[field] = selected(value);
}

function onSearchFieldChange(value: unknown): void {
    local.searchBy = toSearchField(selected(value));

    if (local.search.trim() !== '') {
        apply();
    }
}

function clearAll(): void {
    emit('apply', {
        search_by: null,
        search: null,
        status: null,
        gateway: null,
        tour_date_id: null,
        from: null,
        to: null,
    });
}
</script>

<template>
    <section
        class="space-y-3 border-b border-border p-4"
        :aria-label="t('Filtrar transacciones')"
    >
        <form
            class="grid gap-3 sm:grid-cols-[minmax(0,14rem)_minmax(0,1fr)_auto]"
            @submit.prevent="apply"
        >
            <div class="space-y-1.5">
                <Label for="transaction-search-by" class="text-xs">
                    {{ $t('Buscar por') }}
                </Label>
                <Select
                    :model-value="local.searchBy"
                    @update:model-value="onSearchFieldChange"
                >
                    <SelectTrigger id="transaction-search-by" class="w-full">
                        <SelectValue :placeholder="$t('Buscar por')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in props.searchFields"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="space-y-1.5">
                <Label for="transaction-search" class="text-xs">
                    {{ $t('Valor') }}
                </Label>
                <div class="relative">
                    <Search
                        class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        id="transaction-search"
                        v-model="local.search"
                        type="search"
                        class="pl-9"
                        maxlength="64"
                        :placeholder="
                            $t('Pegá el valor exacto y presioná Enter')
                        "
                    />
                </div>
            </div>

            <div class="flex items-end">
                <Button type="submit" size="sm" class="w-full sm:w-auto">
                    {{ $t('Buscar') }}
                </Button>
            </div>
        </form>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div class="space-y-1.5">
                <Label for="transaction-status" class="text-xs">
                    {{ $t('Estado') }}
                </Label>
                <Select
                    :model-value="local.status"
                    @update:model-value="(value) => onSelect('status', value)"
                >
                    <SelectTrigger id="transaction-status" class="w-full">
                        <SelectValue :placeholder="$t('Todos')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">{{ $t('Todos') }}</SelectItem>
                        <SelectItem
                            v-for="option in props.statuses"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="space-y-1.5">
                <Label for="transaction-gateway" class="text-xs">
                    {{ $t('Medio de pago') }}
                </Label>
                <Select
                    :model-value="local.gateway"
                    @update:model-value="(value) => onSelect('gateway', value)"
                >
                    <SelectTrigger id="transaction-gateway" class="w-full">
                        <SelectValue :placeholder="$t('Todos')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">{{ $t('Todos') }}</SelectItem>
                        <SelectItem
                            v-for="option in props.gateways"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="space-y-1.5">
                <Label for="transaction-departure" class="text-xs">
                    {{ $t('Salida') }}
                </Label>
                <Select
                    :model-value="local.tourDateId"
                    @update:model-value="
                        (value) => onSelect('tourDateId', value)
                    "
                >
                    <SelectTrigger id="transaction-departure" class="w-full">
                        <SelectValue :placeholder="$t('Todas')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">{{ $t('Todas') }}</SelectItem>
                        <SelectItem
                            v-for="option in props.departures"
                            :key="option.value"
                            :value="String(option.value)"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="space-y-1.5">
                <Label for="transaction-from" class="text-xs">
                    {{ $t('Desde') }}
                </Label>
                <Input
                    id="transaction-from"
                    v-model="local.from"
                    type="date"
                    :disabled="ignoresDateRange"
                    :aria-describedby="rangeHintId"
                />
            </div>

            <div class="space-y-1.5">
                <Label for="transaction-to" class="text-xs">
                    {{ $t('Hasta') }}
                </Label>
                <Input
                    id="transaction-to"
                    v-model="local.to"
                    type="date"
                    :disabled="ignoresDateRange"
                    :aria-invalid="invalidRange"
                    :aria-describedby="rangeHintId"
                />
            </div>
        </div>

        <p v-if="invalidRange" class="text-xs text-destructive" role="alert">
            {{ $t('La fecha final no puede ser anterior a la inicial.') }}
        </p>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <p
                id="transaction-range-hint"
                class="text-xs text-muted-foreground"
            >
                <span v-if="ignoresDateRange">
                    {{
                        $t(
                            'Al buscar por :field se busca en todo el historial.',
                            { field: searchFieldLabel },
                        )
                    }}
                </span>
                <span v-else-if="showsDefaultWindow">
                    {{
                        $t('Mostrando los últimos :days días.', {
                            days: props.defaultDays,
                        })
                    }}
                </span>
            </p>

            <Button
                v-if="hasActiveFilters"
                variant="ghost"
                size="sm"
                @click="clearAll"
            >
                {{ $t('Limpiar filtros') }}
            </Button>
        </div>
    </section>
</template>
