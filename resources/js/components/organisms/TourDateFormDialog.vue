<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import {
    store as storeDate,
    update as updateDate,
} from '@/actions/App/Http/Controllers/Admin/TourDatePagesController';
import GuideSelect from '@/components/molecules/GuideSelect.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/composables/useTranslations';
import { formatCurrency } from '@/lib/format';
import type { DepartureRange } from '@/types/guide-availability';
import type {
    DepartureDefaults,
    LogisticsRef,
    TourDateAdmin,
    TourRouteRef,
} from '@/types/logistics';

const { t } = useTranslations();

type Props = {
    open: boolean;
    tourId: number;
    editing: TourDateAdmin | null;
    /**
     * La duración del tour, que es de donde sale el fin de la salida (D9). Sin
     * ella se cae a la de la salida que se edita.
     */
    durationHours?: number | null;
    /**
     * Lo que la salida nueva hereda del producto y de la agencia (spec §G):
     * guía, capacidad, ruta predeterminada, precio base de referencia y mínimo
     * de abono. Todo es propuesta: se puede cambiar antes de guardar.
     */
    departureDefaults: DepartureDefaults;
    /** Solo las rutas del producto; el servidor rechaza cualquier otra. */
    tourRoutes: TourRouteRef[];
    guides: LogisticsRef[];
    providers: LogisticsRef[];
    hotels: LogisticsRef[];
};

const props = withDefaults(defineProps<Props>(), {
    durationHours: null,
});

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [];
}>();

const MS_PER_HOUR = 3_600_000;

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

function toDateOnly(date: Date): string {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function toDateTimeLocal(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function toIso(local: string): string | null {
    if (local === '') {
        return null;
    }

    const date = new Date(local);

    return Number.isNaN(date.getTime()) ? null : date.toISOString();
}

const form = useForm({
    starts_at: '',
    capacity: props.departureDefaults.capacity,
    price_override: '',
    min_payment_pct: '' as string | number,
    notes: '',
    guide_id: null as number | null,
    route_id: null as number | null,
    provider_id: null as number | null,
    hotel_ids: [] as number[],
});

const processing = computed(() => form.processing);
const isEditing = computed(() => props.editing !== null);
const localErrors = ref<Partial<Record<string, string>>>({});

const errors = computed<Record<string, string | undefined>>(() => ({
    ...(form.errors as Record<string, string | undefined>),
    ...localErrors.value,
}));

const basePriceLabel = computed(() =>
    formatCurrency(
        props.departureDefaults.base_price,
        props.departureDefaults.currency,
    ),
);

const agencyMinPaymentPct = computed(
    () => props.departureDefaults.min_payment_pct,
);

/**
 * WHY (D9): el fin ya no se escribe, se deriva. Cuando el tour no viaja en las
 * props —la lista global de salidas solo edita— se recupera de la salida que se
 * está editando, que trae el fin que derivó el servidor.
 */
const durationHours = computed<number | null>(() => {
    if (props.durationHours !== null) {
        return props.durationHours;
    }

    const date = props.editing;

    if (date === null || date.ends_at === null) {
        return null;
    }

    const span =
        new Date(date.ends_at).getTime() - new Date(date.starts_at).getTime();

    return Number.isNaN(span) ? null : Math.round(span / MS_PER_HOUR);
});

const derivedEnd = computed<Date | null>(() => {
    if (form.starts_at === '' || durationHours.value === null) {
        return null;
    }

    const start = new Date(form.starts_at);

    if (Number.isNaN(start.getTime())) {
        return null;
    }

    return new Date(start.getTime() + durationHours.value * MS_PER_HOUR);
});

/** Los días calendario que la salida le ocupará al guía. */
const guideRange = computed<DepartureRange | null>(() => {
    if (form.starts_at === '') {
        return null;
    }

    const start = new Date(form.starts_at);

    if (Number.isNaN(start.getTime())) {
        return null;
    }

    const end = derivedEnd.value ?? start;

    return { from: toDateOnly(start), to: toDateOnly(end) };
});

const derivedEndLabel = computed(() =>
    derivedEnd.value === null
        ? t('Se calcula con la duración del tour.')
        : derivedEnd.value.toLocaleString(),
);

function resetFromEditing(): void {
    localErrors.value = {};
    form.clearErrors();
    const date = props.editing;

    if (date === null) {
        form.starts_at = '';
        form.capacity = props.departureDefaults.capacity;
        form.price_override = '';
        form.min_payment_pct = '';
        form.notes = '';
        form.guide_id = props.departureDefaults.guide_id;
        form.route_id = props.departureDefaults.route_id;
        form.provider_id = null;
        form.hotel_ids = [];

        return;
    }

    form.starts_at = toDateTimeLocal(date.starts_at);
    form.capacity = date.capacity;
    form.price_override = date.price_override ?? '';
    form.min_payment_pct =
        date.min_payment_pct === null ? '' : String(date.min_payment_pct);
    form.notes = date.notes ?? '';
    form.guide_id = date.guide?.id ?? null;
    form.route_id = date.route?.id ?? null;
    form.provider_id = date.provider?.id ?? null;
    form.hotel_ids = date.hotels.map((hotel) => hotel.id);
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            resetFromEditing();
        }
    },
);

function close(): void {
    emit('update:open', false);
}

function parseSelectId(value: string): number | null {
    return value === '' ? null : Number(value);
}

/** Vacío → `null`: la salida vuelve al porcentaje de la agencia. */
function minPaymentPctPayload(): number | null {
    const trimmed = String(form.min_payment_pct).trim();

    if (trimmed === '') {
        return null;
    }

    const parsed = Number(trimmed);

    return Number.isNaN(parsed) ? null : Math.trunc(parsed);
}

function toggleHotel(hotelId: number): void {
    const index = form.hotel_ids.indexOf(hotelId);

    if (index === -1) {
        form.hotel_ids.push(hotelId);

        return;
    }

    form.hotel_ids.splice(index, 1);
}

function validateLocally(): boolean {
    localErrors.value = {};

    if (form.starts_at === '') {
        localErrors.value.starts_at = t('La fecha de inicio es obligatoria.');
    }

    if (form.capacity < 1) {
        localErrors.value.capacity = t('La capacidad debe ser al menos 1.');
    }

    // Espejo de `StoreTourDateRequest`: el servidor sigue siendo la fuente de
    // verdad, esto solo evita el viaje de ida y vuelta.
    const minPaymentPct = minPaymentPctPayload();

    if (minPaymentPct !== null && (minPaymentPct < 1 || minPaymentPct > 100)) {
        localErrors.value.min_payment_pct = t(
            'El mínimo de abono debe estar entre 1 y 100.',
        );
    }

    // D7: no existe «Sin asignar». El servidor lo rechaza igual; pedirlo acá
    // evita perder el formulario entero por un campo vacío.
    if (form.guide_id === null) {
        localErrors.value.guide_id = t('Elige un guía para la salida.');
    }

    return Object.keys(localErrors.value).length === 0;
}

function submit(): void {
    if (form.processing || !validateLocally()) {
        return;
    }

    const editing = props.editing;

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(
                editing === null
                    ? t('Salida creada.')
                    : t('Salida actualizada.'),
            );
            emit('saved');
            close();
        },
        onError: () => {
            toast.error(t('Revisa los campos marcados.'));
        },
    };

    const submitted = form.transform((data) => ({
        ...data,
        starts_at: toIso(String(data.starts_at)),
        price_override:
            String(data.price_override).trim() === ''
                ? null
                : String(data.price_override).trim(),
        min_payment_pct: minPaymentPctPayload(),
        notes: String(data.notes).trim() === '' ? null : String(data.notes).trim(),
    }));

    if (editing !== null) {
        submitted.put(updateDate(editing.id).url, options);

        return;
    }

    submitted.post(storeDate(props.tourId).url, options);
}
</script>

<template>
    <Dialog
        :open="props.open"
        @update:open="(value) => emit('update:open', value)"
    >
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ isEditing ? $t('Editar salida') : $t('Nueva salida') }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Define la fecha, la capacidad y las condiciones especiales de esta salida.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <p
                    v-if="errors._global"
                    class="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive"
                >
                    {{ errors._global }}
                </p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label for="date-starts-at">{{ $t('Inicio *') }}</Label>
                        <Input
                            id="date-starts-at"
                            v-model="form.starts_at"
                            type="datetime-local"
                        />
                        <p
                            v-if="errors.starts_at"
                            class="text-xs text-destructive"
                        >
                            {{ errors.starts_at }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <Label>{{ $t('Fin') }}</Label>
                        <p
                            class="flex h-10 items-center rounded-md border border-dashed border-input px-3 text-sm text-muted-foreground"
                        >
                            {{ derivedEndLabel }}
                        </p>
                        <p
                            v-if="errors.ends_at"
                            class="text-xs text-destructive"
                        >
                            {{ errors.ends_at }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="date-capacity">{{
                            $t('Capacidad *')
                        }}</Label>
                        <Input
                            id="date-capacity"
                            v-model.number="form.capacity"
                            type="number"
                            min="1"
                            max="500"
                        />
                        <p
                            v-if="errors.capacity"
                            class="text-xs text-destructive"
                        >
                            {{ errors.capacity }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="date-price">{{
                            $t('Precio propio')
                        }}</Label>
                        <Input
                            id="date-price"
                            v-model="form.price_override"
                            type="text"
                            inputmode="decimal"
                            :placeholder="
                                $t('Usa el precio base si se deja vacío')
                            "
                        />
                        <p
                            v-if="errors.price_override"
                            class="text-xs text-destructive"
                        >
                            {{ errors.price_override }}
                        </p>
                        <p v-else class="text-xs text-muted-foreground">
                            {{
                                $t('Precio base del producto: :price', {
                                    price: basePriceLabel,
                                })
                            }}
                        </p>
                    </div>

                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="date-min-payment-pct">
                            {{ $t('Mínimo de abono (%)') }}
                        </Label>
                        <Input
                            id="date-min-payment-pct"
                            v-model="form.min_payment_pct"
                            type="number"
                            min="1"
                            max="100"
                            step="1"
                            inputmode="numeric"
                            :aria-describedby="
                                errors.min_payment_pct
                                    ? 'date-min-payment-pct-error'
                                    : 'date-min-payment-pct-hint'
                            "
                            :placeholder="
                                $t('Por defecto: :percent%', {
                                    percent: agencyMinPaymentPct,
                                })
                            "
                        />
                        <p
                            v-if="errors.min_payment_pct"
                            id="date-min-payment-pct-error"
                            class="text-xs text-destructive"
                        >
                            {{ errors.min_payment_pct }}
                        </p>
                        <p
                            v-else
                            id="date-min-payment-pct-hint"
                            class="text-xs text-muted-foreground"
                        >
                            {{
                                $t(
                                    'Porcentaje del total que el viajero debe abonar para asegurar esta salida.',
                                )
                            }}
                        </p>
                    </div>
                </div>

                <GuideSelect
                    id="date-guide"
                    v-model="form.guide_id"
                    :range="guideRange"
                    :exclude-tour-date-id="props.editing?.id ?? null"
                    :fallback-guides="props.guides"
                    :error="errors.guide_id"
                />

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label for="date-route">{{ $t('Ruta') }}</Label>
                        <select
                            id="date-route"
                            :value="form.route_id ?? ''"
                            class="flex h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                            @change="
                                form.route_id = parseSelectId(
                                    ($event.target as HTMLSelectElement).value,
                                )
                            "
                        >
                            <option value="">{{ $t('Sin ruta') }}</option>
                            <option
                                v-for="route in props.tourRoutes"
                                :key="route.id"
                                :value="route.id"
                            >
                                {{ route.name }}
                                <template v-if="route.is_default">
                                    · {{ $t('predeterminada') }}
                                </template>
                            </option>
                        </select>
                        <p
                            v-if="errors.route_id"
                            class="text-xs text-destructive"
                        >
                            {{ errors.route_id }}
                        </p>
                        <p
                            v-else-if="props.tourRoutes.length === 0"
                            class="text-xs text-muted-foreground"
                        >
                            {{
                                $t(
                                    'Este producto todavía no tiene rutas asociadas.',
                                )
                            }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="date-provider">{{ $t('Proveedor') }}</Label>
                        <select
                            id="date-provider"
                            :value="form.provider_id ?? ''"
                            class="flex h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                            @change="
                                form.provider_id = parseSelectId(
                                    ($event.target as HTMLSelectElement).value,
                                )
                            "
                        >
                            <option value="">{{ $t('Sin proveedor') }}</option>
                            <option
                                v-for="provider in props.providers"
                                :key="provider.id"
                                :value="provider.id"
                            >
                                {{ provider.name }}
                            </option>
                        </select>
                        <p
                            v-if="errors.provider_id"
                            class="text-xs text-destructive"
                        >
                            {{ errors.provider_id }}
                        </p>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <Label>{{ $t('Hoteles') }}</Label>
                    <p
                        v-if="props.hotels.length === 0"
                        class="text-xs text-muted-foreground"
                    >
                        {{ $t('No hay hoteles en tu catálogo todavía.') }}
                    </p>
                    <div
                        v-else
                        class="max-h-40 space-y-1 overflow-y-auto rounded-md border border-input p-2"
                    >
                        <button
                            v-for="hotel in props.hotels"
                            :key="hotel.id"
                            type="button"
                            class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm transition hover:bg-muted"
                            @click="toggleHotel(hotel.id)"
                        >
                            <Checkbox
                                :model-value="form.hotel_ids.includes(hotel.id)"
                                class="pointer-events-none"
                            />
                            <span>{{ hotel.name }}</span>
                        </button>
                    </div>
                    <p v-if="errors.hotel_ids" class="text-xs text-destructive">
                        {{ errors.hotel_ids }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <Label for="date-notes">{{ $t('Notas') }}</Label>
                    <Textarea
                        id="date-notes"
                        v-model="form.notes"
                        rows="3"
                        :placeholder="
                            $t('Detalles internos de esta salida (opcional)')
                        "
                    />
                    <p v-if="errors.notes" class="text-xs text-destructive">
                        {{ errors.notes }}
                    </p>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="processing"
                        @click="close"
                    >
                        {{ $t('Cancelar') }}
                    </Button>
                    <Button type="submit" :disabled="processing">
                        {{
                            processing
                                ? $t('Guardando…')
                                : isEditing
                                  ? $t('Guardar salida')
                                  : $t('Crear salida')
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
