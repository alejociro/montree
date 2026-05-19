<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { CheckCircle, Mail, Pencil, Phone, User, X, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { store as storePayment } from '@/actions/App/Http/Controllers/Api/V1/PaymentController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import PublicLayout from '@/layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

type BookingProp = {
    booking_number: string;
    status: string;
    total_amount: string;
    currency: string;
    expires_at: string | null;
    tour_name: string;
    starts_at: string;
    travelers_count: number;
};

const props = defineProps<{ booking: BookingProp }>();

const page = usePage();
const authUser = computed(
    () =>
        page.props.auth?.user as
            | { name: string; email: string; phone?: string }
            | undefined,
);

const PARTIAL_PAYMENT_PERCENT = 0.5;

const paymentType = ref<'full' | 'partial'>('full');
const couponCode = ref('');
const partialAmount = ref(0);
const discount = ref(0);

const totalNumeric = computed(() => Number(props.booking.total_amount));

const minPartialAmount = computed(() =>
    Math.ceil(totalNumeric.value * PARTIAL_PAYMENT_PERCENT),
);

const subtotal = computed(() => totalNumeric.value);
const totalAfterDiscount = computed(() =>
    Math.max(0, subtotal.value - discount.value),
);

const amountToPay = computed(() => {
    if (paymentType.value === 'full') {
        return totalAfterDiscount.value;
    }
    const amt = partialAmount.value || minPartialAmount.value;
    return Math.min(totalAfterDiscount.value, Math.max(minPartialAmount.value, amt));
});

const pendingBalance = computed(() =>
    Math.max(0, totalAfterDiscount.value - amountToPay.value),
);

// Ensure partialAmount starts at minimum when switching to partial
function selectPaymentType(type: 'full' | 'partial') {
    paymentType.value = type;
    if (type === 'partial' && partialAmount.value < minPartialAmount.value) {
        partialAmount.value = minPartialAmount.value;
    }
}

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: props.booking.currency,
        maximumFractionDigits: 0,
    }).format(amount);
}

const formattedTotal = computed(() => formatCurrency(totalNumeric.value));

const isPendingPayment = computed(() => props.booking.status === 'pending_payment');
const isSuccess = computed(() =>
    ['confirmed', 'completed'].includes(props.booking.status),
);
const isCancelled = computed(() =>
    ['expired', 'cancelled'].includes(props.booking.status),
);

const processing = ref(false);

async function handlePay(): Promise<void> {
    if (processing.value) {
        return;
    }

    processing.value = true;

    const action = storePayment(props.booking.booking_number);
    const csrf = decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );

    try {
        const response = await fetch(action.url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': csrf,
            },
            body: JSON.stringify({
                type: paymentType.value === 'partial' ? 'partial' : 'full',
                amount: paymentType.value === 'partial' ? amountToPay.value : undefined,
            }),
        });

        if (response.ok) {
            toast.success('Pago procesado correctamente.');
            router.reload({ only: ['booking'] });
        } else {
            const body = await response.json().catch(() => ({}));
            const firstError = Object.values(body.errors ?? {})[0];
            toast.error(
                Array.isArray(firstError)
                    ? (firstError[0] as string)
                    : (body.message ?? 'No se pudo procesar el pago.'),
            );
        }
    } catch {
        toast.error('Error de conexión al procesar el pago.');
    } finally {
        processing.value = false;
    }
}

function goBack() {
    window.history.back();
}
</script>

<template>
    <Head :title="`Reserva ${booking.booking_number}`" />

    <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-xl font-bold text-foreground sm:text-2xl">
                Resumen de pago
            </h1>
            <button
                type="button"
                class="flex size-9 items-center justify-center rounded-full text-muted-foreground transition hover:bg-muted hover:text-foreground"
                aria-label="Cerrar"
                @click="goBack"
            >
                <X class="size-5" />
            </button>
        </div>

        <!-- Success State -->
        <div
            v-if="isSuccess"
            class="flex flex-col items-center gap-4 rounded-lg border border-green-200 bg-green-50 px-6 py-12 text-center dark:border-green-800 dark:bg-green-950"
        >
            <CheckCircle class="size-12 text-green-600 dark:text-green-400" />
            <h2 class="text-xl font-semibold text-green-800 dark:text-green-200">
                Pago confirmado
            </h2>
            <p class="text-sm text-green-700 dark:text-green-300">
                Tu reserva <strong>{{ booking.booking_number }}</strong> ha
                sido confirmada exitosamente.
            </p>
            <dl class="mt-2 text-sm text-green-700 dark:text-green-300">
                <div class="flex gap-2">
                    <dt class="font-medium">Tour:</dt>
                    <dd>{{ booking.tour_name }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="font-medium">Total:</dt>
                    <dd>{{ formattedTotal }}</dd>
                </div>
            </dl>
            <Badge variant="default" class="mt-2">{{ booking.status }}</Badge>
        </div>

        <!-- Cancelled / Expired State -->
        <div
            v-else-if="isCancelled"
            class="flex flex-col items-center gap-4 rounded-lg border border-red-200 bg-red-50 px-6 py-12 text-center dark:border-red-800 dark:bg-red-950"
        >
            <XCircle class="size-12 text-red-600 dark:text-red-400" />
            <h2 class="text-xl font-semibold text-red-800 dark:text-red-200">
                Reserva {{ booking.status === 'expired' ? 'expirada' : 'cancelada' }}
            </h2>
            <p class="text-sm text-red-700 dark:text-red-300">
                La reserva <strong>{{ booking.booking_number }}</strong>
                {{ booking.status === 'expired' ? 'ha expirado' : 'fue cancelada' }}.
                Por favor, crea una nueva reserva si deseas continuar.
            </p>
            <Badge variant="outline" class="mt-2">{{ booking.status }}</Badge>
        </div>

        <!-- Payment Form (pending_payment) -->
        <div v-else-if="isPendingPayment" class="grid gap-6 lg:grid-cols-5">
            <!-- LEFT COLUMN -->
            <div class="space-y-6 lg:col-span-3">
                <!-- Datos de reserva -->
                <section class="rounded-lg border border-border bg-card p-5">
                    <h2 class="mb-4 text-base font-semibold text-card-foreground">
                        Datos de reserva
                    </h2>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="flex items-start gap-3">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted">
                                <User class="size-4 text-muted-foreground" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs text-muted-foreground">Nombre</p>
                                <p class="truncate text-sm font-medium text-card-foreground">
                                    {{ authUser?.name ?? '---' }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="shrink-0 text-muted-foreground transition hover:text-foreground"
                                aria-label="Editar nombre"
                            >
                                <Pencil class="size-3.5" />
                            </button>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted">
                                <Phone class="size-4 text-muted-foreground" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs text-muted-foreground">Teléfono</p>
                                <p class="truncate text-sm font-medium text-card-foreground">
                                    {{ authUser?.phone ?? 'No registrado' }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="shrink-0 text-muted-foreground transition hover:text-foreground"
                                aria-label="Editar teléfono"
                            >
                                <Pencil class="size-3.5" />
                            </button>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted">
                                <Mail class="size-4 text-muted-foreground" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs text-muted-foreground">Correo</p>
                                <p class="truncate text-sm font-medium text-card-foreground">
                                    {{ authUser?.email ?? '---' }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="shrink-0 text-muted-foreground transition hover:text-foreground"
                                aria-label="Editar correo"
                            >
                                <Pencil class="size-3.5" />
                            </button>
                        </div>
                    </div>
                </section>

                <!-- Información de la actividad -->
                <section class="rounded-lg border border-border bg-card p-5">
                    <h2 class="mb-4 text-base font-semibold text-card-foreground">
                        Información de la actividad
                    </h2>
                    <dl class="grid gap-3 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-muted-foreground">Actividad</dt>
                            <dd class="font-medium text-card-foreground">
                                {{ booking.tour_name }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-muted-foreground">Fecha</dt>
                            <dd class="text-card-foreground">
                                {{
                                    new Date(booking.starts_at).toLocaleDateString(
                                        'es-CO',
                                        {
                                            weekday: 'long',
                                            year: 'numeric',
                                            month: 'long',
                                            day: 'numeric',
                                        },
                                    )
                                }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-muted-foreground">Viajeros</dt>
                            <dd class="text-card-foreground">
                                {{ booking.travelers_count }}
                                {{ booking.travelers_count === 1 ? 'persona' : 'personas' }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between border-t border-border pt-3">
                            <dt class="text-muted-foreground">Precio total</dt>
                            <dd class="text-lg font-bold text-primary">
                                {{ formattedTotal }}
                            </dd>
                        </div>
                    </dl>
                </section>
            </div>

            <!-- RIGHT COLUMN -->
            <div class="space-y-6 lg:col-span-2">
                <!-- Resumen de compra -->
                <section class="rounded-lg border border-border bg-card p-5">
                    <h2 class="mb-4 text-base font-semibold text-card-foreground">
                        Resumen de compra
                    </h2>
                    <dl class="grid gap-2 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-muted-foreground">Subtotal</dt>
                            <dd class="text-card-foreground">
                                {{ formatCurrency(subtotal) }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-muted-foreground">Descuentos</dt>
                            <dd class="text-card-foreground">
                                {{ discount > 0 ? `- ${formatCurrency(discount)}` : formatCurrency(0) }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between border-t border-border pt-2">
                            <dt class="font-semibold text-card-foreground">Total</dt>
                            <dd class="text-lg font-bold text-card-foreground">
                                {{ formatCurrency(totalAfterDiscount) }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <!-- Detalles del pago -->
                <section class="rounded-lg border border-border bg-card p-5">
                    <h2 class="mb-4 text-base font-semibold text-card-foreground">
                        Detalles del pago
                    </h2>

                    <!-- Payment type radio group (native, no RadioGroup component) -->
                    <div class="space-y-3">
                        <label
                            class="flex cursor-pointer items-center gap-3 rounded-md border px-4 py-3 transition"
                            :class="
                                paymentType === 'full'
                                    ? 'border-primary bg-primary/5'
                                    : 'border-border hover:border-muted-foreground/30'
                            "
                        >
                            <input
                                type="radio"
                                name="payment_type"
                                value="full"
                                :checked="paymentType === 'full'"
                                class="size-4 accent-primary"
                                @change="selectPaymentType('full')"
                            />
                            <div>
                                <span class="text-sm font-medium text-card-foreground">
                                    Pago total
                                </span>
                                <p class="text-xs text-muted-foreground">
                                    {{ formatCurrency(totalAfterDiscount) }}
                                </p>
                            </div>
                        </label>

                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-md border px-4 py-3 transition"
                            :class="
                                paymentType === 'partial'
                                    ? 'border-primary bg-primary/5'
                                    : 'border-border hover:border-muted-foreground/30'
                            "
                        >
                            <input
                                type="radio"
                                name="payment_type"
                                value="partial"
                                :checked="paymentType === 'partial'"
                                class="mt-0.5 size-4 accent-primary"
                                @change="selectPaymentType('partial')"
                            />
                            <div>
                                <span class="text-sm font-medium text-card-foreground">
                                    Valor mínimo de reserva
                                </span>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    Paga al menos el {{ PARTIAL_PAYMENT_PERCENT * 100 }}%
                                    para asegurar tu reserva. El saldo restante podrás
                                    pagarlo antes de la fecha de la actividad.
                                </p>
                            </div>
                        </label>
                    </div>

                    <!-- Partial amount input -->
                    <div
                        v-if="paymentType === 'partial'"
                        class="mt-4 space-y-3"
                    >
                        <div>
                            <label
                                for="partial-amount"
                                class="text-xs text-muted-foreground"
                            >
                                Monto a pagar (mín.
                                {{ formatCurrency(minPartialAmount) }})
                            </label>
                            <Input
                                id="partial-amount"
                                v-model.number="partialAmount"
                                type="number"
                                :min="minPartialAmount"
                                :max="totalAfterDiscount"
                                class="mt-1"
                            />
                        </div>
                        <div class="flex items-center justify-between rounded-md bg-muted/50 px-3 py-2 text-sm">
                            <span class="text-muted-foreground">Saldo pendiente</span>
                            <span class="font-semibold text-card-foreground">
                                {{ formatCurrency(pendingBalance) }}
                            </span>
                        </div>
                    </div>

                    <!-- Coupon code -->
                    <!-- TODO: Integrate coupon validation API (F007) -->
                    <div class="mt-4">
                        <Input
                            v-model="couponCode"
                            type="text"
                            placeholder="Ingresa tu Código de descuento"
                        />
                    </div>

                    <!-- Pay button -->
                    <Button
                        class="mt-5 w-full"
                        size="lg"
                        :disabled="processing"
                        @click="handlePay"
                    >
                        {{ processing ? 'Procesando...' : `Pagar ${formatCurrency(amountToPay)}` }}
                    </Button>

                    <!-- Payment methods -->
                    <div class="mt-4 text-center">
                        <p class="mb-3 text-xs text-muted-foreground">
                            Recibimos todos los medios de pago y también efectivo
                        </p>
                        <div class="flex items-center justify-center gap-2">
                            <!-- Visa -->
                            <svg
                                class="h-6 w-10"
                                viewBox="0 0 40 24"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <rect
                                    width="40"
                                    height="24"
                                    rx="3"
                                    fill="#1A1F71"
                                />
                                <path
                                    d="M17.3 15.7h-2.1l1.3-8h2.1l-1.3 8zm-3.8-8l-2 5.5-.2-1.2-.7-3.7s-.1-.6-.9-.6h-3.2l-.1.2s.9.2 1.9.8l1.7 6h2.2l3.3-8h-2zm19.1 8h1.9l-1.7-8h-1.7c-.6 0-1 .3-1.2.8l-3.1 7.2h2.2l.4-1.2h2.7l.5 1.2zm-2.3-2.8l1.1-3.1.6 3.1h-1.7zm-3.5-3.4l.3-1.7s-.9-.3-1.8-.3c-1 0-3.4.4-3.4 2.5 0 2 2.7 2 2.7 3s-2.4.8-3.2.2l-.3 1.8s.9.4 2.3.4c1.4 0 3.5-.7 3.5-2.6 0-2-2.7-2.2-2.7-3s1.9-.7 2.6-.3z"
                                    fill="white"
                                />
                            </svg>
                            <!-- Mastercard -->
                            <svg
                                class="h-6 w-10"
                                viewBox="0 0 40 24"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <rect
                                    width="40"
                                    height="24"
                                    rx="3"
                                    fill="#252525"
                                />
                                <circle
                                    cx="16"
                                    cy="12"
                                    r="6"
                                    fill="#EB001B"
                                />
                                <circle
                                    cx="24"
                                    cy="12"
                                    r="6"
                                    fill="#F79E1B"
                                />
                                <path
                                    d="M20 7.5a5.96 5.96 0 012 4.5 5.96 5.96 0 01-2 4.5 5.96 5.96 0 01-2-4.5 5.96 5.96 0 012-4.5z"
                                    fill="#FF5F00"
                                />
                            </svg>
                            <!-- American Express -->
                            <svg
                                class="h-6 w-10"
                                viewBox="0 0 40 24"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <rect
                                    width="40"
                                    height="24"
                                    rx="3"
                                    fill="#016FD0"
                                />
                                <text
                                    x="20"
                                    y="14"
                                    text-anchor="middle"
                                    font-size="6"
                                    font-weight="bold"
                                    fill="white"
                                    font-family="Arial, sans-serif"
                                >
                                    AMEX
                                </text>
                            </svg>
                            <!-- PSE -->
                            <svg
                                class="h-6 w-10"
                                viewBox="0 0 40 24"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <rect
                                    width="40"
                                    height="24"
                                    rx="3"
                                    fill="#00A651"
                                />
                                <text
                                    x="20"
                                    y="14"
                                    text-anchor="middle"
                                    font-size="7"
                                    font-weight="bold"
                                    fill="white"
                                    font-family="Arial, sans-serif"
                                >
                                    PSE
                                </text>
                            </svg>
                            <!-- Nequi -->
                            <svg
                                class="h-6 w-10"
                                viewBox="0 0 40 24"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <rect
                                    width="40"
                                    height="24"
                                    rx="3"
                                    fill="#E6007E"
                                />
                                <text
                                    x="20"
                                    y="14"
                                    text-anchor="middle"
                                    font-size="5.5"
                                    font-weight="bold"
                                    fill="white"
                                    font-family="Arial, sans-serif"
                                >
                                    Nequi
                                </text>
                            </svg>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <!-- Fallback for unknown status -->
        <div
            v-else
            class="rounded-lg border border-border bg-card px-6 py-12 text-center"
        >
            <p class="text-sm text-muted-foreground">
                Estado de la reserva: <strong>{{ booking.status }}</strong>
            </p>
        </div>
    </div>
</template>
