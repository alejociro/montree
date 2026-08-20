<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CheckCircle2, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { syncTravelers } from '@/actions/App/Http/Controllers/Api/V1/BookingController';
import { Badge } from '@/components/ui/badge';
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
import { useApi } from '@/composables/useApi';
import { useTranslations } from '@/composables/useTranslations';
import type { BookingTraveler, TravelerSyncInput } from '@/types/booking';

const { t } = useTranslations();

type Props = {
    bookingNumber: string;
    adultsCount: number;
    minorsCount: number;
    travelers: BookingTraveler[];
    required: boolean;
    contact?: { name: string; phone: string | null } | null;
};

const props = defineProps<Props>();

const api = useApi();

type TravelerSlot = {
    id: number | null;
    full_name: string;
    phone: string;
    document_type: string;
    document_number: string;
    birth_date: string;
    is_minor: boolean;
};

const DOCUMENT_TYPES = [
    { value: 'cc', label: t('Cédula de ciudadanía') },
    { value: 'ce', label: t('Cédula de extranjería') },
    { value: 'ti', label: t('Tarjeta de identidad') },
    { value: 'passport', label: t('Pasaporte') },
    { value: 'other', label: t('Otro') },
];

function blankSlot(isMinor: boolean): TravelerSlot {
    return {
        id: null,
        full_name: '',
        phone: '',
        document_type: '',
        document_number: '',
        birth_date: '',
        is_minor: isMinor,
    };
}

function slotFromTraveler(traveler: BookingTraveler): TravelerSlot {
    return {
        id: traveler.id,
        full_name: traveler.full_name ?? '',
        phone: traveler.phone ?? '',
        document_type: traveler.document_type ?? '',
        document_number: traveler.document_number ?? '',
        birth_date: traveler.birth_date ?? '',
        is_minor: traveler.is_minor,
    };
}

/**
 * WHY: on a group booking the first adult is always the person filling the
 * form, so retyping their own name and phone is pure friction. Only seeded on
 * an empty first slot — a saved traveler always wins.
 */
function seededFirstAdult(): TravelerSlot {
    const slot = blankSlot(false);

    if (props.contact) {
        slot.full_name = props.contact.name;
        slot.phone = props.contact.phone ?? '';
    }

    return slot;
}

function buildSlots(): TravelerSlot[] {
    const existingAdults = props.travelers.filter((t) => !t.is_minor);
    const existingMinors = props.travelers.filter((t) => t.is_minor);

    const adultSlots = Array.from({ length: props.adultsCount }, (_, index) => {
        if (existingAdults[index]) {
            return slotFromTraveler(existingAdults[index]);
        }

        return index === 0 && existingAdults.length === 0
            ? seededFirstAdult()
            : blankSlot(false);
    });

    const minorSlots = Array.from({ length: props.minorsCount }, (_, index) =>
        existingMinors[index]
            ? slotFromTraveler(existingMinors[index])
            : blankSlot(true),
    );

    return [...adultSlots, ...minorSlots];
}

const slots = ref<TravelerSlot[]>(buildSlots());
const saving = ref(false);
const generalError = ref<string | null>(null);

watch(
    () => props.travelers,
    () => {
        slots.value = buildSlots();
    },
    { deep: true },
);

const adultsCompleted = computed(
    () =>
        slots.value.filter((s) => !s.is_minor && s.full_name.trim() !== '')
            .length,
);

const minorsCompleted = computed(
    () =>
        slots.value.filter((s) => s.is_minor && s.full_name.trim() !== '')
            .length,
);

const totalCompleted = computed(
    () => adultsCompleted.value + minorsCompleted.value,
);

const totalSlots = computed(() => props.adultsCount + props.minorsCount);

const allCompleted = computed(
    () => totalCompleted.value === totalSlots.value && totalSlots.value > 0,
);

function optionalField(value: string): string | undefined {
    const trimmed = value.trim();

    return trimmed === '' ? undefined : trimmed;
}

function buildPayload(): TravelerSyncInput[] {
    return slots.value
        .filter((slot) => slot.full_name.trim() !== '')
        .map((slot) => {
            const traveler: TravelerSyncInput = {
                full_name: slot.full_name.trim(),
                is_minor: slot.is_minor,
            };

            if (slot.id !== null) {
                traveler.id = slot.id;
            }

            const phone = optionalField(slot.phone);

            if (phone !== undefined) {
                traveler.phone = phone;
            }

            const documentType = optionalField(slot.document_type);

            if (documentType !== undefined) {
                traveler.document_type = documentType;
            }

            const documentNumber = optionalField(slot.document_number);

            if (documentNumber !== undefined) {
                traveler.document_number = documentNumber;
            }

            const birthDate = optionalField(slot.birth_date);

            if (birthDate !== undefined) {
                traveler.birth_date = birthDate;
            }

            return traveler;
        });
}

function save(): void {
    if (saving.value) {
        return;
    }

    const payload = buildPayload();

    if (payload.length === 0) {
        generalError.value = t(
            'Completa el nombre de al menos un viajero para guardar.',
        );
        toast.error(generalError.value);

        return;
    }

    saving.value = true;
    generalError.value = null;

    void api.put(
        syncTravelers(props.bookingNumber).url,
        { travelers: payload },
        {
            onSuccess: () => {
                toast.success(t('Datos de los viajeros guardados.'));
                router.reload({ only: ['booking'] });
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0] ?? null;
                generalError.value =
                    firstError ?? t('No pudimos guardar los datos.');
                toast.error(generalError.value);
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

function slotLabel(slot: TravelerSlot, index: number): string {
    const adultsBefore = slots.value
        .slice(0, index + 1)
        .filter((s) => !s.is_minor).length;
    const minorsBefore = slots.value
        .slice(0, index + 1)
        .filter((s) => s.is_minor).length;

    return slot.is_minor ? `Menor ${minorsBefore}` : `Adulto ${adultsBefore}`;
}
</script>

<template>
    <section class="space-y-4 rounded-lg border border-border bg-card p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <Users class="size-5 text-primary" />
                <h2 class="text-base font-semibold text-foreground">
                    {{ $t('Viajeros') }}
                </h2>
                <Badge v-if="required" variant="destructive">
                    {{ $t('Requerido antes del tour') }}
                </Badge>
                <Badge v-else variant="outline">{{ $t('Opcional') }}</Badge>
            </div>
            <div class="flex items-center gap-2 text-sm">
                <CheckCircle2 v-if="allCompleted" class="size-4 text-primary" />
                <span class="text-muted-foreground">
                    {{
                        $t(':done de :total completados', {
                            done: totalCompleted,
                            total: totalSlots,
                        })
                    }}
                </span>
            </div>
        </div>

        <p class="text-sm text-muted-foreground">
            {{
                $t(
                    'Adultos: :adultsDone/:adults · Menores: :minorsDone/:minors',
                    {
                        adultsDone: adultsCompleted,
                        adults: adultsCount,
                        minorsDone: minorsCompleted,
                        minors: minorsCount,
                    },
                )
            }}
        </p>

        <p
            v-if="required && !allCompleted"
            class="rounded-md bg-destructive/10 px-3 py-2 text-xs text-destructive"
        >
            {{
                $t(
                    'Debes completar los datos de todos los viajeros antes de la fecha del tour.',
                )
            }}
        </p>

        <form class="space-y-4" @submit.prevent="save">
            <div
                v-for="(slot, index) in slots"
                :key="index"
                class="space-y-3 rounded-lg border border-border bg-background p-4"
            >
                <p class="text-sm font-medium text-foreground">
                    {{ slotLabel(slot, index) }}
                </p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5 sm:col-span-2">
                        <Label :for="`traveler-name-${index}`">
                            {{ $t('Nombre completo *') }}
                        </Label>
                        <Input
                            :id="`traveler-name-${index}`"
                            v-model="slot.full_name"
                            type="text"
                            :placeholder="$t('Como aparece en el documento')"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label :for="`traveler-phone-${index}`">
                            {{ $t('Teléfono') }}
                        </Label>
                        <Input
                            :id="`traveler-phone-${index}`"
                            v-model="slot.phone"
                            type="tel"
                            placeholder="+57 300 000 0000"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label :for="`traveler-birth-${index}`">
                            {{ $t('Fecha de nacimiento') }}
                        </Label>
                        <Input
                            :id="`traveler-birth-${index}`"
                            v-model="slot.birth_date"
                            type="date"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label :for="`traveler-doc-type-${index}`">
                            {{ $t('Tipo de documento') }}
                        </Label>
                        <Select v-model="slot.document_type">
                            <SelectTrigger :id="`traveler-doc-type-${index}`">
                                <SelectValue :placeholder="$t('Seleccionar')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="type in DOCUMENT_TYPES"
                                    :key="type.value"
                                    :value="type.value"
                                >
                                    {{ type.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-1.5">
                        <Label :for="`traveler-doc-number-${index}`">
                            {{ $t('Número de documento') }}
                        </Label>
                        <Input
                            :id="`traveler-doc-number-${index}`"
                            v-model="slot.document_number"
                            type="text"
                        />
                    </div>
                </div>
            </div>

            <p v-if="generalError" class="text-sm text-destructive">
                {{ generalError }}
            </p>

            <Button type="submit" :disabled="saving">
                {{
                    saving ? $t('Guardando…') : $t('Guardar datos de viajeros')
                }}
            </Button>
        </form>
    </section>
</template>
