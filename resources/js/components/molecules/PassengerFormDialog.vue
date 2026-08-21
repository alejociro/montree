<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import {
    store as storePassenger,
    update as updatePassenger,
} from '@/actions/App/Http/Controllers/Api/V1/Admin/PassengerController';
import { Button } from '@/components/ui/button';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { ApiErrors } from '@/composables/useApi';
import { useApi } from '@/composables/useApi';
import { useTranslations } from '@/composables/useTranslations';
import type { DocumentType, Eps } from '@/types/booking';
import {
    DOCUMENT_TYPES,
    EPS_OPTIONS,
    EPS_REQUIRING_DETAIL,
} from '@/types/booking';
import type { Passenger, PassengerFormInput } from '@/types/passenger';

const { t } = useTranslations();

type Props = {
    open: boolean;
    /** `null` = alta sobre la reserva `bookingNumber`. */
    passenger: Passenger | null;
    bookingNumber: string | null;
    canViewMedical: boolean;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [];
}>();

const api = useApi();

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

function blankForm(): PassengerFormInput {
    return {
        full_name: '',
        is_minor: false,
        document_type: '',
        document_number: '',
        birth_date: '',
        email: '',
        phone: '',
        emergency_contact_name: '',
        emergency_contact_relationship: '',
        emergency_contact_phone: '',
        eps: '',
        eps_other: '',
        medical_notes: '',
    };
}

const form = reactive<PassengerFormInput>(blankForm());
const processing = ref(false);
const errors = ref<ApiErrors>({});

const isEditing = computed(() => props.passenger?.id != null);

function asDocumentType(value: DocumentType | null): DocumentType | '' {
    return DOCUMENT_TYPES.find((type) => type === value) ?? '';
}

function asEps(value: Eps | null | undefined): Eps | '' {
    return EPS_OPTIONS.find((option) => option === value) ?? '';
}

watch(
    () => [props.open, props.passenger] as const,
    () => {
        if (!props.open) {
            return;
        }

        errors.value = {};
        Object.assign(form, blankForm());

        const passenger = props.passenger;

        if (passenger === null) {
            return;
        }

        form.full_name = passenger.full_name;
        form.is_minor = passenger.is_minor ?? false;
        form.document_type = asDocumentType(passenger.document_type);
        form.document_number = passenger.document_number ?? '';
        form.email = passenger.email ?? '';
        form.phone = passenger.phone ?? '';
        form.emergency_contact_name = passenger.emergency_contact_name ?? '';
        form.emergency_contact_relationship =
            passenger.emergency_contact_relationship ?? '';
        form.emergency_contact_phone = passenger.emergency_contact_phone ?? '';
        form.eps = asEps(passenger.eps);
        form.eps_other = passenger.eps_other ?? '';
        form.medical_notes = passenger.medical_notes ?? '';
    },
    { immediate: true },
);

/**
 * Misma regla del backend (`UpdatePassengerAction`): con una EPS que no es
 * «Otra» el texto libre se anula, así que aquí desaparece en vez de quedarse
 * escrito sin efecto.
 */
function selectEps(value: Eps): void {
    form.eps = value;

    if (value !== EPS_REQUIRING_DETAIL) {
        form.eps_other = '';

        return;
    }

    void nextTick(() => {
        document.getElementById('passenger-eps-other')?.focus();
    });
}

/** Espejo de las dos reglas que el usuario puede romper sin darse cuenta. */
const localErrors = computed<Record<string, string>>(() => {
    const found: Record<string, string> = {};

    if (form.full_name.trim() === '') {
        found.full_name = t('El nombre completo es obligatorio.');
    }

    if (
        form.emergency_contact_name.trim() !== '' &&
        form.emergency_contact_phone.trim() === ''
    ) {
        found.emergency_contact_phone = t(
            'Indica el teléfono del contacto de emergencia.',
        );
    }

    if (form.eps === EPS_REQUIRING_DETAIL && form.eps_other.trim() === '') {
        found.eps_other = t('Indica el nombre de la EPS.');
    }

    return found;
});

const submitted = ref(false);

function errorFor(field: string): string | undefined {
    return (
        errors.value[field] ??
        (submitted.value ? localErrors.value[field] : undefined)
    );
}

// WHY: los nombres de campo se resuelven en el script y no dentro de `{{ }}`.
// `TranslationCatalogTest` marca cualquier literal en una interpolación como
// copy sin traducir, y tiene razón en marcarlo: la excepción sería la regla.
const nameError = computed(() => errorFor('full_name'));
const emailError = computed(() => errorFor('email'));
const emergencyPhoneError = computed(() => errorFor('emergency_contact_phone'));
const epsOtherError = computed(() => errorFor('eps_other'));

function optional(value: string): string | null {
    const trimmed = value.trim();

    return trimmed === '' ? null : trimmed;
}

function buildPayload(): Record<string, unknown> {
    const payload: Record<string, unknown> = {
        full_name: form.full_name.trim(),
        is_minor: form.is_minor,
        document_type: form.document_type === '' ? null : form.document_type,
        document_number: optional(form.document_number),
        birth_date: optional(form.birth_date),
        email: optional(form.email),
        phone: optional(form.phone),
        emergency_contact_name: optional(form.emergency_contact_name),
        emergency_contact_relationship: optional(
            form.emergency_contact_relationship,
        ),
        emergency_contact_phone: optional(form.emergency_contact_phone),
    };

    // Sin el permiso médico estos tres campos ni se mandan. El backend los
    // descartaría igual (máscara de escritura, D7); no mandarlos evita además
    // que el formulario prometa guardar algo que no va a guardar.
    if (props.canViewMedical) {
        payload.eps = form.eps === '' ? null : form.eps;
        payload.eps_other =
            form.eps === EPS_REQUIRING_DETAIL ? optional(form.eps_other) : null;
        payload.medical_notes = optional(form.medical_notes);
    }

    return payload;
}

function close(): void {
    emit('update:open', false);
}

function submit(): void {
    if (processing.value) {
        return;
    }

    submitted.value = true;

    if (Object.keys(localErrors.value).length > 0) {
        return;
    }

    const passengerId = props.passenger?.id ?? null;
    const bookingNumber = props.bookingNumber;

    if (passengerId === null && bookingNumber === null) {
        return;
    }

    processing.value = true;
    errors.value = {};

    const url =
        passengerId !== null
            ? updatePassenger.url(passengerId)
            : storePassenger.url(bookingNumber as string);

    const send = passengerId !== null ? api.put : api.post;

    void send(url, buildPayload(), {
        onSuccess: () => {
            submitted.value = false;
            toast.success(
                passengerId !== null
                    ? t('Pasajero actualizado.')
                    : t('Pasajero agregado.'),
            );
            emit('saved');
            close();
        },
        onError: (received) => {
            errors.value = received;

            if (received._global) {
                toast.error(received._global);
            }
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <Dialog
        :open="props.open"
        @update:open="(value) => emit('update:open', value)"
    >
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>
                    {{
                        isEditing
                            ? $t('Editar pasajero')
                            : $t('Agregar pasajero')
                    }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Los datos quedan en la planilla de la salida y en el CSV que se imprime.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="passenger-name">
                            {{ $t('Nombre completo *') }}
                        </Label>
                        <Input
                            id="passenger-name"
                            v-model="form.full_name"
                            type="text"
                            :aria-invalid="nameError !== undefined"
                        />
                        <p v-if="nameError" class="text-xs text-destructive">
                            {{ nameError }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="passenger-doc-type">
                            {{ $t('Tipo de documento') }}
                        </Label>
                        <Select v-model="form.document_type">
                            <SelectTrigger
                                id="passenger-doc-type"
                                class="w-full"
                            >
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
                        <Label for="passenger-doc-number">
                            {{ $t('Número de documento') }}
                        </Label>
                        <Input
                            id="passenger-doc-number"
                            v-model="form.document_number"
                            type="text"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="passenger-email">
                            {{ $t('Correo electrónico') }}
                        </Label>
                        <Input
                            id="passenger-email"
                            v-model="form.email"
                            type="email"
                            :aria-invalid="emailError !== undefined"
                        />
                        <p v-if="emailError" class="text-xs text-destructive">
                            {{ emailError }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="passenger-phone">
                            {{ $t('Teléfono') }}
                        </Label>
                        <Input
                            id="passenger-phone"
                            v-model="form.phone"
                            type="tel"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="passenger-birth">
                            {{ $t('Fecha de nacimiento') }}
                        </Label>
                        <Input
                            id="passenger-birth"
                            v-model="form.birth_date"
                            type="date"
                        />
                    </div>

                    <div class="flex items-end">
                        <label
                            class="flex cursor-pointer items-center gap-2 text-sm"
                        >
                            <input
                                v-model="form.is_minor"
                                type="checkbox"
                                class="size-4 accent-primary"
                            />
                            <span>{{ $t('Es menor de edad') }}</span>
                        </label>
                    </div>
                </div>

                <fieldset class="space-y-3 border-t border-border pt-4">
                    <legend class="text-sm font-medium text-foreground">
                        {{ $t('Contacto de emergencia') }}
                    </legend>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="space-y-1.5">
                            <Label for="passenger-emergency-name">
                                {{ $t('Nombre') }}
                            </Label>
                            <Input
                                id="passenger-emergency-name"
                                v-model="form.emergency_contact_name"
                                type="text"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="passenger-emergency-relationship">
                                {{ $t('Parentesco') }}
                            </Label>
                            <Input
                                id="passenger-emergency-relationship"
                                v-model="form.emergency_contact_relationship"
                                type="text"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="passenger-emergency-phone">
                                {{ $t('Teléfono') }}
                            </Label>
                            <Input
                                id="passenger-emergency-phone"
                                v-model="form.emergency_contact_phone"
                                type="tel"
                                :aria-invalid="
                                    emergencyPhoneError !== undefined
                                "
                            />
                            <p
                                v-if="emergencyPhoneError"
                                class="text-xs text-destructive"
                            >
                                {{ emergencyPhoneError }}
                            </p>
                        </div>
                    </div>
                </fieldset>

                <!--
                  El bloque de salud solo existe con el permiso médico (D7).
                  Sin él, `sales` no puede leerlo ni escribirlo: el formulario
                  no lo dibuja y el payload no lo lleva.
                -->
                <fieldset
                    v-if="props.canViewMedical"
                    class="space-y-3 border-t border-border pt-4"
                >
                    <legend class="text-sm font-medium text-foreground">
                        {{ $t('EPS y observaciones') }}
                    </legend>

                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <label
                            v-for="option in EPS_OPTIONS"
                            :key="option"
                            class="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm transition"
                            :class="
                                form.eps === option
                                    ? 'border-primary bg-primary/5'
                                    : 'border-border hover:border-muted-foreground/30'
                            "
                        >
                            <input
                                type="radio"
                                name="passenger-eps"
                                :value="option"
                                :checked="form.eps === option"
                                class="size-4 accent-primary"
                                @change="selectEps(option)"
                            />
                            <span>{{ EPS_LABELS[option] }}</span>
                        </label>
                    </div>

                    <div
                        v-if="form.eps === EPS_REQUIRING_DETAIL"
                        class="space-y-1.5 sm:max-w-sm"
                    >
                        <Label for="passenger-eps-other">
                            {{ $t('¿Cuál EPS?') }}
                        </Label>
                        <Input
                            id="passenger-eps-other"
                            v-model="form.eps_other"
                            type="text"
                            :aria-invalid="epsOtherError !== undefined"
                        />
                        <p
                            v-if="epsOtherError"
                            class="text-xs text-destructive"
                        >
                            {{ epsOtherError }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="passenger-medical-notes">
                            {{ $t('Observaciones médicas') }}
                        </Label>
                        <Textarea
                            id="passenger-medical-notes"
                            v-model="form.medical_notes"
                            rows="3"
                            :placeholder="
                                $t(
                                    'Alergias, condiciones médicas o discapacidad física.',
                                )
                            "
                        />
                    </div>
                </fieldset>

                <p v-if="errors._global" class="text-sm text-destructive">
                    {{ errors._global }}
                </p>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="close">
                        {{ $t('Cancelar') }}
                    </Button>
                    <Button type="submit" :disabled="processing">
                        {{ processing ? $t('Guardando…') : $t('Guardar') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
