<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { store as storePayment } from '@/actions/App/Http/Controllers/Api/V1/Admin/BookingPaymentController';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import PaymentStatusChip from '@/components/molecules/PaymentStatusChip.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import type { ApiErrors } from '@/composables/useApi';
import { useApi } from '@/composables/useApi';
import { useTranslations } from '@/composables/useTranslations';
import { formatCurrency, formatTourDate } from '@/lib/format';
import type { ManualPaymentInput, Passenger } from '@/types/passenger';

const { t } = useTranslations();

type Props = {
    open: boolean;
    passenger: Passenger | null;
    canViewMedical: boolean;
    readonly: boolean;
    canEdit: boolean;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    edit: [passenger: Passenger];
    'payment-registered': [];
}>();

const api = useApi();

const isPending = computed(() => props.passenger?.id === null);

const payment = computed(() => props.passenger?.payment ?? null);

const medicalNote = computed(() => {
    const note = props.passenger?.medical_notes;

    return note && note.trim() !== '' ? note : null;
});

const epsLabel = computed(() => {
    const passenger = props.passenger;

    if (!passenger?.eps) {
        return null;
    }

    return passenger.eps === 'other' && passenger.eps_other
        ? passenger.eps_other
        : (passenger.eps_label ?? null);
});

const paymentForm = reactive<ManualPaymentInput>({
    amount: '',
    reference: '',
    paid_at: '',
});

const showPaymentForm = ref(false);
const processing = ref(false);
const errors = ref<ApiErrors>({});

watch(
    () => props.passenger,
    () => {
        showPaymentForm.value = false;
        errors.value = {};
        paymentForm.amount = '';
        paymentForm.reference = '';
        paymentForm.paid_at = '';
    },
);

function openPaymentForm(): void {
    showPaymentForm.value = true;
    paymentForm.amount = payment.value?.due_amount ?? '';
}

function registerPayment(): void {
    const bookingNumber = props.passenger?.booking_number;

    if (processing.value || !bookingNumber) {
        return;
    }

    processing.value = true;
    errors.value = {};

    void api.post(
        storePayment.url(bookingNumber),
        {
            amount: paymentForm.amount,
            reference: paymentForm.reference.trim() || null,
            paid_at: paymentForm.paid_at || null,
        },
        {
            onSuccess: () => {
                toast.success(t('Pago registrado.'));
                showPaymentForm.value = false;
                emit('payment-registered');
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
        },
    );
}
</script>

<template>
    <Sheet
        :open="props.open"
        @update:open="(value) => emit('update:open', value)"
    >
        <SheetContent class="w-full overflow-y-auto sm:max-w-md">
            <template v-if="props.passenger">
                <SheetHeader>
                    <SheetTitle>{{ props.passenger.full_name }}</SheetTitle>
                    <SheetDescription>
                        <template v-if="isPending">
                            {{
                                $t(
                                    'La reserva existe pero sus viajeros todavía no se cargaron.',
                                )
                            }}
                        </template>
                        <template
                            v-else-if="props.passenger.departure_starts_at"
                        >
                            {{
                                formatTourDate(
                                    props.passenger.departure_starts_at,
                                )
                            }}
                        </template>
                    </SheetDescription>
                </SheetHeader>

                <div class="space-y-6 px-4 pb-6">
                    <section class="space-y-2">
                        <MonoLabel>{{ $t('Identificación') }}</MonoLabel>
                        <dl class="grid gap-2 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-muted-foreground">
                                    {{ $t('Documento') }}
                                </dt>
                                <dd class="text-right font-medium">
                                    {{
                                        props.passenger.document_number ??
                                        $t('Sin registrar')
                                    }}
                                </dd>
                            </div>
                            <div
                                v-if="props.passenger.document_type_label"
                                class="flex justify-between gap-3"
                            >
                                <dt class="text-muted-foreground">
                                    {{ $t('Tipo') }}
                                </dt>
                                <dd class="text-right">
                                    {{ props.passenger.document_type_label }}
                                </dd>
                            </div>
                            <div
                                v-if="props.passenger.booking_number"
                                class="flex justify-between gap-3"
                            >
                                <dt class="text-muted-foreground">
                                    {{ $t('Reserva') }}
                                </dt>
                                <dd class="text-right font-mono text-xs">
                                    {{ props.passenger.booking_number }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section class="space-y-2">
                        <MonoLabel>{{ $t('Contacto') }}</MonoLabel>
                        <dl class="grid gap-2 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-muted-foreground">
                                    {{ $t('Correo electrónico') }}
                                </dt>
                                <dd class="text-right break-all">
                                    {{
                                        props.passenger.email ??
                                        $t('Sin registrar')
                                    }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-muted-foreground">
                                    {{ $t('Teléfono') }}
                                </dt>
                                <dd class="text-right">
                                    {{
                                        props.passenger.phone ??
                                        $t('Sin registrar')
                                    }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section class="space-y-2">
                        <MonoLabel>{{
                            $t('Contacto de emergencia')
                        }}</MonoLabel>
                        <dl class="grid gap-2 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-muted-foreground">
                                    {{ $t('Nombre') }}
                                </dt>
                                <dd class="text-right">
                                    {{
                                        props.passenger
                                            .emergency_contact_name ??
                                        $t('Sin registrar')
                                    }}
                                </dd>
                            </div>
                            <div
                                v-if="
                                    props.passenger
                                        .emergency_contact_relationship
                                "
                                class="flex justify-between gap-3"
                            >
                                <dt class="text-muted-foreground">
                                    {{ $t('Parentesco') }}
                                </dt>
                                <dd class="text-right">
                                    {{
                                        props.passenger
                                            .emergency_contact_relationship
                                    }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-muted-foreground">
                                    {{ $t('Teléfono') }}
                                </dt>
                                <dd class="text-right">
                                    {{
                                        props.passenger
                                            .emergency_contact_phone ??
                                        $t('Sin registrar')
                                    }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <!-- Bloque de salud: existe solo con el permiso médico (D7). -->
                    <section v-if="props.canViewMedical" class="space-y-2">
                        <MonoLabel>{{ $t('Salud') }}</MonoLabel>
                        <dl class="grid gap-2 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-muted-foreground">
                                    {{ $t('EPS') }}
                                </dt>
                                <dd class="text-right">
                                    {{ epsLabel ?? $t('Sin registrar') }}
                                </dd>
                            </div>
                        </dl>
                        <div
                            v-if="medicalNote"
                            class="rounded-lg border border-brand-drop/25 bg-brand-drop-50 p-3"
                        >
                            <MonoLabel class="text-brand-drop">
                                {{ $t('Observaciones médicas') }}
                            </MonoLabel>
                            <p
                                class="mt-1.5 text-sm font-semibold whitespace-pre-line text-brand-drop"
                            >
                                {{ medicalNote }}
                            </p>
                        </div>
                        <p
                            v-if="props.passenger.dietary_restrictions"
                            class="text-sm text-muted-foreground"
                        >
                            {{
                                $t('Alimentación: :value', {
                                    value: props.passenger.dietary_restrictions,
                                })
                            }}
                        </p>
                    </section>

                    <section v-if="payment" class="space-y-2">
                        <MonoLabel>{{ $t('Estado de pago') }}</MonoLabel>
                        <PaymentStatusChip :status="payment.status" />
                        <dl class="grid gap-2 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-muted-foreground">
                                    {{ $t('Su parte') }}
                                </dt>
                                <dd class="text-right tabular-nums">
                                    {{
                                        formatCurrency(
                                            payment.share_amount,
                                            payment.currency,
                                        )
                                    }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-muted-foreground">
                                    {{ $t('Abonado') }}
                                </dt>
                                <dd class="text-right tabular-nums">
                                    {{
                                        formatCurrency(
                                            payment.paid_amount,
                                            payment.currency,
                                        )
                                    }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-muted-foreground">
                                    {{ $t('Saldo') }}
                                </dt>
                                <dd
                                    class="text-right font-semibold tabular-nums"
                                    :class="
                                        payment.status === 'due'
                                            ? 'text-brand-drop'
                                            : 'text-brand-green-600'
                                    "
                                >
                                    {{
                                        formatCurrency(
                                            payment.due_amount,
                                            payment.currency,
                                        )
                                    }}
                                </dd>
                            </div>
                        </dl>
                        <p class="text-xs text-muted-foreground">
                            {{
                                $t(
                                    'El saldo es de la reserva completa, no de la persona: se reparte en partes iguales.',
                                )
                            }}
                        </p>
                    </section>

                    <!--
                      La zona del guía SOLO lee: ninguna acción de escritura se
                      dibuja ahí (D1).
                    -->
                    <section
                        v-if="!props.readonly && props.canEdit"
                        class="space-y-3 border-t border-border pt-4"
                    >
                        <div class="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="emit('edit', props.passenger)"
                            >
                                {{
                                    isPending
                                        ? $t('Completar datos')
                                        : $t('Editar pasajero')
                                }}
                            </Button>
                            <Button
                                v-if="
                                    payment &&
                                    payment.status === 'due' &&
                                    !showPaymentForm
                                "
                                size="sm"
                                @click="openPaymentForm"
                            >
                                {{ $t('Registrar pago') }}
                            </Button>
                        </div>

                        <form
                            v-if="showPaymentForm"
                            class="space-y-3 rounded-lg border border-border p-3"
                            @submit.prevent="registerPayment"
                        >
                            <div class="space-y-1.5">
                                <Label for="manual-payment-amount">
                                    {{ $t('Monto') }}
                                </Label>
                                <Input
                                    id="manual-payment-amount"
                                    v-model="paymentForm.amount"
                                    type="text"
                                    inputmode="decimal"
                                    :aria-invalid="errors.amount !== undefined"
                                />
                                <p
                                    v-if="errors.amount"
                                    class="text-xs text-destructive"
                                >
                                    {{ errors.amount }}
                                </p>
                            </div>
                            <div class="space-y-1.5">
                                <Label for="manual-payment-reference">
                                    {{ $t('Referencia') }}
                                </Label>
                                <Input
                                    id="manual-payment-reference"
                                    v-model="paymentForm.reference"
                                    type="text"
                                    :placeholder="
                                        $t('Transferencia, recibo, efectivo…')
                                    "
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="manual-payment-date">
                                    {{ $t('Fecha del pago') }}
                                </Label>
                                <Input
                                    id="manual-payment-date"
                                    v-model="paymentForm.paid_at"
                                    type="date"
                                />
                            </div>
                            <p
                                v-if="errors._global"
                                class="text-xs text-destructive"
                            >
                                {{ errors._global }}
                            </p>
                            <div class="flex gap-2">
                                <Button type="submit" :disabled="processing">
                                    {{
                                        processing
                                            ? $t('Guardando…')
                                            : $t('Guardar')
                                    }}
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    @click="showPaymentForm = false"
                                >
                                    {{ $t('Cancelar') }}
                                </Button>
                            </div>
                        </form>
                    </section>
                </div>
            </template>
        </SheetContent>
    </Sheet>
</template>
