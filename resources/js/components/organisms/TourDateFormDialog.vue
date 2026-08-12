<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import {
    store as storeDate,
    update as updateDate,
} from '@/actions/App/Http/Controllers/Api/V1/Admin/TourDateController';
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
import { useApi  } from '@/composables/useApi';
import type {ApiErrors} from '@/composables/useApi';
import type {
    LogisticsRef,
    TourDateAdmin,
    TourDateFormInput,
} from '@/types/logistics';

type Props = {
    open: boolean;
    tourId: number;
    editing: TourDateAdmin | null;
    guides: LogisticsRef[];
    routes: LogisticsRef[];
    providers: LogisticsRef[];
    hotels: LogisticsRef[];
};

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [date: TourDateAdmin];
}>();

const api = useApi();

const processing = ref(false);
const errors = ref<ApiErrors>({});

const form = reactive<TourDateFormInput>({
    starts_at: '',
    ends_at: '',
    capacity: 10,
    price_override: '',
    notes: '',
    guide_id: null,
    route_id: null,
    provider_id: null,
    hotel_ids: [],
});

const isEditing = computed(() => props.editing !== null);

function pad(value: number): string {
    return String(value).padStart(2, '0');
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

function resetFromEditing(): void {
    errors.value = {};
    const date = props.editing;

    if (date === null) {
        form.starts_at = '';
        form.ends_at = '';
        form.capacity = 10;
        form.price_override = '';
        form.notes = '';
        form.guide_id = null;
        form.route_id = null;
        form.provider_id = null;
        form.hotel_ids = [];

        return;
    }

    form.starts_at = toDateTimeLocal(date.starts_at);
    form.ends_at = toDateTimeLocal(date.ends_at);
    form.capacity = date.capacity;
    form.price_override = date.price_override ?? '';
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

function toggleHotel(hotelId: number): void {
    const index = form.hotel_ids.indexOf(hotelId);

    if (index === -1) {
        form.hotel_ids.push(hotelId);

        return;
    }

    form.hotel_ids.splice(index, 1);
}

function validateLocally(): boolean {
    errors.value = {};

    if (form.starts_at === '') {
        errors.value.starts_at = 'La fecha de inicio es obligatoria.';
    }

    if (form.capacity < 1) {
        errors.value.capacity = 'La capacidad debe ser al menos 1.';
    }

    return Object.keys(errors.value).length === 0;
}

function buildPayload(): Record<string, unknown> {
    return {
        starts_at: toIso(form.starts_at),
        ends_at: toIso(form.ends_at),
        capacity: form.capacity,
        price_override:
            form.price_override.trim() === ''
                ? null
                : form.price_override.trim(),
        notes: form.notes.trim() === '' ? null : form.notes.trim(),
        guide_id: form.guide_id,
        route_id: form.route_id,
        provider_id: form.provider_id,
        hotel_ids: form.hotel_ids,
    };
}

function submit(): void {
    if (processing.value || !validateLocally()) {
        return;
    }

    processing.value = true;
    const payload = buildPayload();

    const options = {
        onSuccess: (response: { data: TourDateAdmin } | null) => {
            if (!response) {
                return;
            }

            toast.success(
                isEditing.value ? 'Salida actualizada.' : 'Salida creada.',
            );
            emit('saved', response.data);
            close();
        },
        onError: (received: ApiErrors) => {
            errors.value = received;
            toast.error(
                received._global ?? 'Revisa los campos marcados.',
            );
        },
        onFinish: () => {
            processing.value = false;
        },
    };

    if (isEditing.value) {
        void api.put<{ data: TourDateAdmin }>(
            updateDate(props.editing!.id).url,
            payload,
            options,
        );

        return;
    }

    void api.post<{ data: TourDateAdmin }>(
        storeDate(props.tourId).url,
        payload,
        options,
    );
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
                    {{ isEditing ? 'Editar salida' : 'Nueva salida' }}
                </DialogTitle>
                <DialogDescription>
                    Define la fecha, la capacidad y las condiciones especiales de
                    esta salida.
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
                        <Label for="date-starts-at">Inicio *</Label>
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
                        <Label for="date-ends-at">Fin</Label>
                        <Input
                            id="date-ends-at"
                            v-model="form.ends_at"
                            type="datetime-local"
                        />
                        <p
                            v-if="errors.ends_at"
                            class="text-xs text-destructive"
                        >
                            {{ errors.ends_at }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="date-capacity">Capacidad *</Label>
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
                        <Label for="date-price">Precio propio</Label>
                        <Input
                            id="date-price"
                            v-model="form.price_override"
                            type="text"
                            inputmode="decimal"
                            placeholder="Usa el precio base si se deja vacío"
                        />
                        <p
                            v-if="errors.price_override"
                            class="text-xs text-destructive"
                        >
                            {{ errors.price_override }}
                        </p>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <Label for="date-guide">Guía</Label>
                    <select
                        id="date-guide"
                        :value="form.guide_id ?? ''"
                        class="flex h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                        @change="
                            form.guide_id = parseSelectId(
                                ($event.target as HTMLSelectElement).value,
                            )
                        "
                    >
                        <option value="">Sin guía asignado</option>
                        <option
                            v-for="guide in props.guides"
                            :key="guide.id"
                            :value="guide.id"
                        >
                            {{ guide.name }}
                        </option>
                    </select>
                    <p v-if="errors.guide_id" class="text-xs text-destructive">
                        {{ errors.guide_id }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label for="date-route">Ruta</Label>
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
                            <option value="">Sin ruta</option>
                            <option
                                v-for="route in props.routes"
                                :key="route.id"
                                :value="route.id"
                            >
                                {{ route.name }}
                            </option>
                        </select>
                        <p
                            v-if="errors.route_id"
                            class="text-xs text-destructive"
                        >
                            {{ errors.route_id }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="date-provider">Proveedor</Label>
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
                            <option value="">Sin proveedor</option>
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
                    <Label>Hoteles</Label>
                    <p
                        v-if="props.hotels.length === 0"
                        class="text-xs text-muted-foreground"
                    >
                        No hay hoteles en tu catálogo todavía.
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
                    <Label for="date-notes">Notas</Label>
                    <Textarea
                        id="date-notes"
                        v-model="form.notes"
                        rows="3"
                        placeholder="Detalles internos de esta salida (opcional)"
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
                        Cancelar
                    </Button>
                    <Button type="submit" :disabled="processing">
                        {{
                            processing
                                ? 'Guardando…'
                                : isEditing
                                  ? 'Guardar salida'
                                  : 'Crear salida'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
