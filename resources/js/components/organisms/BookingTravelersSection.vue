<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CheckCircle2, Users } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
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
import { Textarea } from '@/components/ui/textarea';
import { useApi } from '@/composables/useApi';
import { useTranslations } from '@/composables/useTranslations';
import type {
    BookingTraveler,
    DocumentType,
    Eps,
    TravelerSyncInput,
} from '@/types/booking';
import {
    DOCUMENT_TYPES,
    EPS_OPTIONS,
    EPS_REQUIRING_DETAIL,
} from '@/types/booking';

const { t } = useTranslations();

type Props = {
    bookingNumber: string;
    adultsCount: number;
    minorsCount: number;
    travelers: BookingTraveler[];
    required: boolean;
    contact?: {
        name: string;
        email?: string | null;
        phone: string | null;
        emergency_contact_name?: string | null;
        emergency_contact_phone?: string | null;
    } | null;
};

const props = defineProps<Props>();

const api = useApi();

type TravelerSlot = {
    id: number | null;
    full_name: string;
    email: string;
    phone: string;
    document_type: string;
    document_number: string;
    birth_date: string;
    is_minor: boolean;
    emergency_contact_name: string;
    emergency_contact_relationship: string;
    emergency_contact_phone: string;
    eps: Eps | '';
    eps_other: string;
    medical_notes: string;
};

/**
 * Etiquetas de los enums del backend. Al ser `Record<…>` sobre la unión, quitar
 * o agregar un caso en PHP rompe el type-check hasta actualizar el mapa.
 */
const DOCUMENT_TYPE_LABELS: Record<DocumentType, string> = {
    cc: t('Cédula de ciudadanía'),
    ce: t('Cédula de extranjería'),
    ti: t('Tarjeta de identidad'),
    passport: t('Pasaporte'),
    other: t('Otro'),
};

const EPS_LABELS: Record<Eps, string> = {
    sura: t('Sura'),
    nueva_eps: t('Nueva EPS'),
    sanitas: t('Sanitas'),
    salud_total: t('Salud Total'),
    other: t('Otra'),
};

function asDocumentType(value: string | null): DocumentType | '' {
    return DOCUMENT_TYPES.find((type) => type === value) ?? '';
}

function asEps(value: string | null | undefined): Eps | '' {
    return EPS_OPTIONS.find((option) => option === value) ?? '';
}

function blankSlot(isMinor: boolean): TravelerSlot {
    return {
        id: null,
        full_name: '',
        email: '',
        phone: '',
        document_type: '',
        document_number: '',
        birth_date: '',
        is_minor: isMinor,
        emergency_contact_name: '',
        emergency_contact_relationship: '',
        emergency_contact_phone: '',
        eps: '',
        eps_other: '',
        medical_notes: '',
    };
}

function slotFromTraveler(traveler: BookingTraveler): TravelerSlot {
    return {
        id: traveler.id,
        full_name: traveler.full_name ?? '',
        email: traveler.email ?? '',
        phone: traveler.phone ?? '',
        document_type: asDocumentType(traveler.document_type),
        document_number: traveler.document_number ?? '',
        birth_date: traveler.birth_date ?? '',
        is_minor: traveler.is_minor,
        emergency_contact_name: traveler.emergency_contact_name ?? '',
        emergency_contact_relationship:
            traveler.emergency_contact_relationship ?? '',
        emergency_contact_phone: traveler.emergency_contact_phone ?? '',
        eps: asEps(traveler.eps),
        eps_other: traveler.eps_other ?? '',
        medical_notes: traveler.medical_notes ?? '',
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
        slot.email = props.contact.email ?? '';
        slot.phone = props.contact.phone ?? '';

        // El par nombre + teléfono viaja junto o no viaja: el backend exige el
        // teléfono en cuanto hay nombre (`required_with`).
        if (
            props.contact.emergency_contact_name &&
            props.contact.emergency_contact_phone
        ) {
            slot.emergency_contact_name = props.contact.emergency_contact_name;
            slot.emergency_contact_phone =
                props.contact.emergency_contact_phone;
        }
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

/**
 * WHY: el backend anula `eps_other` cuando la EPS no es «Otra»
 * (`UpdatePassengerAction`). El formulario aplica la misma regla al marcar la
 * opción para que lo que se ve en pantalla sea lo que se guarda, y lleva el foco
 * al campo libre en cuanto aparece.
 */
function selectEps(index: number, value: Eps): void {
    const slot = slots.value[index];

    if (slot === undefined) {
        return;
    }

    slot.eps = value;

    if (value !== EPS_REQUIRING_DETAIL) {
        slot.eps_other = '';

        return;
    }

    void nextTick(() => {
        document.getElementById(`traveler-eps-other-${index}`)?.focus();
    });
}

type SlotErrors = {
    emergency_contact_phone?: string;
    eps_other?: string;
};

/**
 * Espejo de las dos reglas de `SyncBookingTravelersRequest` que el usuario puede
 * romper sin darse cuenta. La fuente de verdad sigue siendo el backend: esto
 * solo evita el viaje de ida y vuelta.
 */
const slotErrors = computed<SlotErrors[]>(() =>
    slots.value.map((slot) => {
        const errors: SlotErrors = {};

        if (slot.full_name.trim() === '') {
            return errors;
        }

        if (
            slot.emergency_contact_name.trim() !== '' &&
            slot.emergency_contact_phone.trim() === ''
        ) {
            errors.emergency_contact_phone = t(
                'Indica el teléfono del contacto de emergencia.',
            );
        }

        if (slot.eps === EPS_REQUIRING_DETAIL && slot.eps_other.trim() === '') {
            errors.eps_other = t('Indica el nombre de tu EPS.');
        }

        return errors;
    }),
);

const hasSlotErrors = computed(() =>
    slotErrors.value.some((errors) => Object.keys(errors).length > 0),
);

const submitted = ref(false);

function errorsFor(index: number): SlotErrors {
    return submitted.value ? (slotErrors.value[index] ?? {}) : {};
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

            const email = optionalField(slot.email);

            if (email !== undefined) {
                traveler.email = email;
            }

            const documentType = asDocumentType(slot.document_type);

            if (documentType !== '') {
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

            const emergencyName = optionalField(slot.emergency_contact_name);

            if (emergencyName !== undefined) {
                traveler.emergency_contact_name = emergencyName;
            }

            const emergencyRelationship = optionalField(
                slot.emergency_contact_relationship,
            );

            if (emergencyRelationship !== undefined) {
                traveler.emergency_contact_relationship = emergencyRelationship;
            }

            const emergencyPhone = optionalField(slot.emergency_contact_phone);

            if (emergencyPhone !== undefined) {
                traveler.emergency_contact_phone = emergencyPhone;
            }

            if (slot.eps !== '') {
                traveler.eps = slot.eps;
            }

            // El texto libre solo viaja con «Otra»: con cualquier otra EPS el
            // backend lo guardaría como `null` de todos modos.
            const epsOther = optionalField(slot.eps_other);

            if (slot.eps === EPS_REQUIRING_DETAIL && epsOther !== undefined) {
                traveler.eps_other = epsOther;
            }

            const medicalNotes = optionalField(slot.medical_notes);

            if (medicalNotes !== undefined) {
                traveler.medical_notes = medicalNotes;
            }

            return traveler;
        });
}

function save(): void {
    if (saving.value) {
        return;
    }

    submitted.value = true;

    const payload = buildPayload();

    if (payload.length === 0) {
        generalError.value = t(
            'Completa el nombre de al menos un viajero para guardar.',
        );
        toast.error(generalError.value);

        return;
    }

    if (hasSlotErrors.value) {
        generalError.value = t('Revisa los datos marcados en rojo.');
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
                submitted.value = false;
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
                        <Label :for="`traveler-email-${index}`">
                            {{ $t('Correo electrónico') }}
                        </Label>
                        <Input
                            :id="`traveler-email-${index}`"
                            v-model="slot.email"
                            type="email"
                            autocomplete="email"
                            placeholder="nombre@correo.com"
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
                                    :key="type"
                                    :value="type"
                                >
                                    {{ DOCUMENT_TYPE_LABELS[type] }}
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

                <fieldset class="space-y-3 border-t border-border pt-4">
                    <legend class="sr-only">
                        {{ $t('Contacto de emergencia') }}
                    </legend>
                    <p class="text-sm font-medium text-foreground">
                        {{ $t('Contacto de emergencia') }}
                    </p>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="space-y-1.5">
                            <Label :for="`traveler-emergency-name-${index}`">
                                {{ $t('Nombre') }}
                            </Label>
                            <Input
                                :id="`traveler-emergency-name-${index}`"
                                v-model="slot.emergency_contact_name"
                                type="text"
                                :placeholder="$t('Quién responde por ti')"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label
                                :for="`traveler-emergency-relationship-${index}`"
                            >
                                {{ $t('Parentesco') }}
                            </Label>
                            <Input
                                :id="`traveler-emergency-relationship-${index}`"
                                v-model="slot.emergency_contact_relationship"
                                type="text"
                                :placeholder="$t('Madre, hermano, amigo…')"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label :for="`traveler-emergency-phone-${index}`">
                                {{ $t('Teléfono') }}
                            </Label>
                            <Input
                                :id="`traveler-emergency-phone-${index}`"
                                v-model="slot.emergency_contact_phone"
                                type="tel"
                                placeholder="+57 300 000 0000"
                                :aria-invalid="
                                    errorsFor(index).emergency_contact_phone !==
                                    undefined
                                "
                                :aria-describedby="
                                    errorsFor(index).emergency_contact_phone
                                        ? `traveler-emergency-phone-error-${index}`
                                        : undefined
                                "
                            />
                            <p
                                v-if="errorsFor(index).emergency_contact_phone"
                                :id="`traveler-emergency-phone-error-${index}`"
                                class="text-xs text-destructive"
                            >
                                {{ errorsFor(index).emergency_contact_phone }}
                            </p>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="space-y-3 border-t border-border pt-4">
                    <legend class="text-sm font-medium text-foreground">
                        {{ $t('EPS') }}
                    </legend>

                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <label
                            v-for="option in EPS_OPTIONS"
                            :key="option"
                            class="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm transition"
                            :class="
                                slot.eps === option
                                    ? 'border-primary bg-primary/5'
                                    : 'border-border hover:border-muted-foreground/30'
                            "
                        >
                            <input
                                type="radio"
                                :name="`traveler-eps-${index}`"
                                :value="option"
                                :checked="slot.eps === option"
                                class="size-4 accent-primary"
                                @change="selectEps(index, option)"
                            />
                            <span class="text-foreground">
                                {{ EPS_LABELS[option] }}
                            </span>
                        </label>
                    </div>

                    <div
                        v-if="slot.eps === EPS_REQUIRING_DETAIL"
                        class="space-y-1.5 sm:max-w-sm"
                    >
                        <Label :for="`traveler-eps-other-${index}`">
                            {{ $t('¿Cuál EPS?') }}
                        </Label>
                        <Input
                            :id="`traveler-eps-other-${index}`"
                            v-model="slot.eps_other"
                            type="text"
                            :placeholder="$t('Nombre de tu EPS')"
                            :aria-invalid="
                                errorsFor(index).eps_other !== undefined
                            "
                            :aria-describedby="
                                errorsFor(index).eps_other
                                    ? `traveler-eps-other-error-${index}`
                                    : undefined
                            "
                        />
                        <p
                            v-if="errorsFor(index).eps_other"
                            :id="`traveler-eps-other-error-${index}`"
                            class="text-xs text-destructive"
                        >
                            {{ errorsFor(index).eps_other }}
                        </p>
                    </div>
                </fieldset>

                <div class="space-y-1.5 border-t border-border pt-4">
                    <Label :for="`traveler-medical-notes-${index}`">
                        {{ $t('Observaciones médicas') }}
                    </Label>
                    <Textarea
                        :id="`traveler-medical-notes-${index}`"
                        v-model="slot.medical_notes"
                        rows="3"
                        :placeholder="
                            $t(
                                'Alergias, condiciones médicas o discapacidad física.',
                            )
                        "
                        :aria-describedby="`traveler-medical-notes-hint-${index}`"
                    />
                    <p
                        :id="`traveler-medical-notes-hint-${index}`"
                        class="text-xs text-muted-foreground"
                    >
                        {{
                            $t(
                                'Visible solo para la agencia y el guía asignado.',
                            )
                        }}
                    </p>
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
