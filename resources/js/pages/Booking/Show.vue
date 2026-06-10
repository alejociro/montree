<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Calendar,
    CheckCircle,
    MapPin,
    Share2,
    X,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { store as storePayment } from '@/actions/App/Http/Controllers/Api/V1/PaymentController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useApi } from '@/composables/useApi';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { formatBookingStatus } from '@/lib/format';

defineOptions({ layout: PublicLayout });

type ContactSnapshot = {
    name: string;
    email: string;
    phone: string | null;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
};

type BookingProp = {
    booking_number: string;
    status: string;
    travelers_count: number;
    subtotal: string;
    discount_amount: string;
    total_amount: string;
    paid_amount: string;
    currency: string;
    expires_at: string | null;
    contact_snapshot: ContactSnapshot | null;
    tour: {
        name: string;
        slug: string;
        meeting_point: string | null;
        cover_image_url: string | null;
    };
    tour_date: {
        starts_at: string;
        ends_at: string | null;
    };
};

const props = defineProps<{ booking: BookingProp }>();

const api = useApi();

const FALLBACK_HERO =
    'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?w=1800&q=80&auto=format&fit=crop';

const PARTIAL_PAYMENT_PERCENT = 0.5;

const isSuccess = computed(() =>
    ['confirmed', 'completed'].includes(props.booking.status),
);
const isPendingPayment = computed(
    () => props.booking.status === 'pending_payment',
);
const isCancelled = computed(() =>
    ['expired', 'cancelled'].includes(props.booking.status),
);

const heroImage = computed(
    () => props.booking.tour.cover_image_url ?? FALLBACK_HERO,
);

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: props.booking.currency,
        maximumFractionDigits: 0,
    }).format(amount);
}

function formatDate(iso: string | null): string {
    if (iso === null) {
        return '—';
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat('es-CO', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

const subtotalNumeric = computed(() => Number(props.booking.subtotal));
const discountNumeric = computed(() => Number(props.booking.discount_amount));
const totalNumeric = computed(() => Number(props.booking.total_amount));
const paidNumeric = computed(() => Number(props.booking.paid_amount));
const pendingBalance = computed(() =>
    Math.max(0, totalNumeric.value - paidNumeric.value),
);

const meetingPoint = computed(() => props.booking.tour.meeting_point ?? '—');

function shareRegistrationLink(): void {
    void navigator.clipboard
        .writeText(window.location.href)
        .then(() => toast.success('Enlace copiado al portapapeles.'))
        .catch(() => toast.error('No pudimos copiar el enlace.'));
}

// Pending payment form state
const paymentType = ref<'full' | 'partial'>('full');
const partialAmount = ref(0);
const processing = ref(false);

const minPartialAmount = computed(() =>
    Math.ceil(totalNumeric.value * PARTIAL_PAYMENT_PERCENT),
);

const amountToPay = computed(() => {
    if (paymentType.value === 'full') {
        return totalNumeric.value;
    }

    const requested = partialAmount.value || minPartialAmount.value;

    return Math.min(
        totalNumeric.value,
        Math.max(minPartialAmount.value, requested),
    );
});

const pendingAfterPartial = computed(() =>
    Math.max(0, totalNumeric.value - amountToPay.value),
);

function selectPaymentType(type: 'full' | 'partial'): void {
    paymentType.value = type;

    if (type === 'partial' && partialAmount.value < minPartialAmount.value) {
        partialAmount.value = minPartialAmount.value;
    }
}

async function handlePay(): Promise<void> {
    if (processing.value) {
        return;
    }

    processing.value = true;

    const paymentAction = storePayment(props.booking.booking_number);

    await api.post(
        paymentAction.url,
        {
            type: paymentType.value,
            amount:
                paymentType.value === 'partial' ? amountToPay.value : undefined,
        },
        {
            onSuccess: () => {
                toast.success('Pago procesado correctamente.');
                router.reload({ only: ['booking'] });
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                toast.error(firstError ?? 'No se pudo procesar el pago.');
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

function goBack(): void {
    window.history.back();
}
</script>

<template>
    <Head :title="`Reserva ${booking.tour.name}`" />

    <div class="bg-background">
        <!-- Success State -->
        <div v-if="isSuccess">
            <!-- Hero -->
            <div
                class="relative flex h-60 items-center justify-center bg-cover bg-center"
                :style="{ backgroundImage: `url(${heroImage})` }"
            >
                <div class="absolute inset-0 bg-[#172e24]/60"></div>
                <div class="relative space-y-2 px-4 text-center text-white">
                    <CheckCircle class="mx-auto size-10" />
                    <h1 class="text-3xl font-bold">
                        ¡Tu reserva ha sido aprobada!
                    </h1>
                    <p class="text-sm text-white/90">
                        Ahora alegra con tu grupo de tu reserva.
                    </p>
                </div>
            </div>

            <div class="container mx-auto max-w-4xl px-4 py-8">
                <!-- Share section -->
                <div
                    class="mb-6 space-y-3 rounded-lg border border-border bg-card p-5"
                >
                    <p class="text-sm text-muted-foreground">
                        Si aún hay viajeros pendientes por registrar, comparte
                        el siguiente enlace para que completen su información.
                    </p>
                    <Button variant="outline" @click="shareRegistrationLink">
                        <Share2 class="size-4" />
                        Compartir enlace de registro
                    </Button>
                </div>

                <p class="mb-6 text-sm text-muted-foreground">
                    Puedes gestionar el pago retentor y consultar los detalles
                    de tu actividad en Mis viajes.
                </p>

                <!-- Date + location summary -->
                <div class="my-6 grid grid-cols-3 gap-4">
                    <div
                        class="space-y-1 rounded-lg border border-border bg-card p-4"
                    >
                        <Calendar class="size-5 text-primary" />
                        <p class="text-xs text-muted-foreground">
                            Fecha del viaje
                        </p>
                        <p class="text-sm font-medium text-foreground">
                            {{ formatDate(booking.tour_date.starts_at) }}
                        </p>
                    </div>
                    <div
                        class="space-y-1 rounded-lg border border-border bg-card p-4"
                    >
                        <Calendar class="size-5 text-primary" />
                        <p class="text-xs text-muted-foreground">
                            Fecha de regreso
                        </p>
                        <p class="text-sm font-medium text-foreground">
                            {{ formatDate(booking.tour_date.ends_at) }}
                        </p>
                    </div>
                    <div
                        class="space-y-1 rounded-lg border border-border bg-card p-4"
                    >
                        <MapPin class="size-5 text-primary" />
                        <p class="text-xs text-muted-foreground">
                            Punto de encuentro
                        </p>
                        <p class="text-sm font-medium text-foreground">
                            {{ meetingPoint }}
                        </p>
                    </div>
                </div>

                <!-- Details + meeting point -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <section
                        class="space-y-3 rounded-lg border border-border bg-card p-5"
                    >
                        <h2 class="text-base font-semibold text-foreground">
                            Detalles de la reserva
                        </h2>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">Quién va</dt>
                                <dd class="font-medium text-foreground">
                                    {{ booking.travelers_count }} personas
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">
                                    ID de reserva
                                </dt>
                                <dd class="font-medium text-foreground">
                                    {{ booking.booking_number }}
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">
                                    ID de pago
                                </dt>
                                <dd class="font-medium text-foreground">—</dd>
                            </div>
                        </dl>
                        <button
                            type="button"
                            class="text-sm text-primary underline underline-offset-2"
                        >
                            Agregar al calendario
                        </button>
                        <div class="border-t border-border pt-3">
                            <p class="text-xs text-muted-foreground">
                                Política de cancelación
                            </p>
                            <p class="text-sm text-foreground">
                                Consulta detalles de políticas aquí
                            </p>
                        </div>
                    </section>

                    <section
                        class="space-y-3 rounded-lg border border-border bg-card p-5"
                    >
                        <h2 class="text-base font-semibold text-foreground">
                            Punto de encuentro
                        </h2>
                        <p class="text-sm text-foreground">
                            {{ meetingPoint }}
                        </p>
                        <div
                            class="flex h-40 items-center justify-center rounded-md bg-muted text-sm text-muted-foreground"
                        >
                            Mapa de encuentro
                        </div>
                        <p class="text-sm text-muted-foreground">
                            Dirección: {{ meetingPoint }}
                        </p>
                    </section>
                </div>

                <!-- Payment + abonos -->
                <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <section
                        class="space-y-2 rounded-lg border border-border bg-card p-5"
                    >
                        <h2
                            class="mb-2 text-base font-semibold text-foreground"
                        >
                            Información de pago
                        </h2>
                        <div class="flex justify-between text-sm">
                            <span class="text-muted-foreground">Subtotal</span>
                            <span class="text-foreground">
                                {{ formatCurrency(subtotalNumeric) }}
                            </span>
                        </div>
                        <div
                            v-if="discountNumeric > 0"
                            class="flex justify-between text-sm"
                        >
                            <span class="text-muted-foreground">Descuento</span>
                            <span class="text-foreground">
                                - {{ formatCurrency(discountNumeric) }}
                            </span>
                        </div>
                        <div
                            class="flex justify-between border-t border-border pt-2 text-sm"
                        >
                            <span class="font-semibold text-foreground">
                                Total
                            </span>
                            <span class="font-bold text-foreground">
                                {{ formatCurrency(totalNumeric) }}
                            </span>
                        </div>
                    </section>

                    <section
                        class="space-y-2 rounded-lg border border-border bg-card p-5"
                    >
                        <h2
                            class="mb-2 text-base font-semibold text-foreground"
                        >
                            Abonos
                        </h2>
                        <div class="flex justify-between text-sm">
                            <span class="text-muted-foreground">
                                Valor abono
                            </span>
                            <span class="text-foreground">
                                {{ formatCurrency(paidNumeric) }}
                            </span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-muted-foreground">
                                Saldo pendiente
                            </span>
                            <span class="font-medium text-foreground">
                                {{ formatCurrency(pendingBalance) }}
                            </span>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <!-- Cancelled / Expired State -->
        <div
            v-else-if="isCancelled"
            class="container mx-auto max-w-2xl px-4 py-16"
        >
            <div
                class="flex flex-col items-center gap-4 rounded-lg border border-destructive/30 bg-destructive/5 px-6 py-12 text-center"
            >
                <XCircle class="size-12 text-destructive" />
                <h2 class="text-xl font-semibold text-foreground">
                    Reserva
                    {{
                        booking.status === 'expired' ? 'expirada' : 'cancelada'
                    }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    La reserva <strong>{{ booking.booking_number }}</strong>
                    {{
                        booking.status === 'expired'
                            ? 'ha expirado'
                            : 'fue cancelada'
                    }}. Por favor, crea una nueva reserva si deseas continuar.
                </p>
                <Badge variant="outline">
                    {{ formatBookingStatus(booking.status) }}
                </Badge>
            </div>
        </div>

        <!-- Pending Payment State -->
        <div
            v-else-if="isPendingPayment"
            class="container mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8"
        >
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

            <div class="grid gap-6 lg:grid-cols-5">
                <!-- LEFT -->
                <div class="space-y-6 lg:col-span-3">
                    <section
                        class="rounded-lg border border-border bg-card p-5"
                    >
                        <h2
                            class="mb-4 text-base font-semibold text-card-foreground"
                        >
                            Información de la actividad
                        </h2>
                        <dl class="grid gap-3 text-sm">
                            <div class="flex items-center justify-between">
                                <dt class="text-muted-foreground">Actividad</dt>
                                <dd class="font-medium text-card-foreground">
                                    {{ booking.tour.name }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="text-muted-foreground">Fecha</dt>
                                <dd class="text-card-foreground">
                                    {{
                                        formatDate(booking.tour_date.starts_at)
                                    }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="text-muted-foreground">Viajeros</dt>
                                <dd class="text-card-foreground">
                                    {{ booking.travelers_count }}
                                    {{
                                        booking.travelers_count === 1
                                            ? 'persona'
                                            : 'personas'
                                    }}
                                </dd>
                            </div>
                            <div
                                class="flex items-center justify-between border-t border-border pt-3"
                            >
                                <dt class="text-muted-foreground">
                                    Precio total
                                </dt>
                                <dd class="text-lg font-bold text-primary">
                                    {{ formatCurrency(totalNumeric) }}
                                </dd>
                            </div>
                        </dl>
                    </section>
                </div>

                <!-- RIGHT -->
                <div class="space-y-6 lg:col-span-2">
                    <section
                        class="rounded-lg border border-border bg-card p-5"
                    >
                        <h2
                            class="mb-4 text-base font-semibold text-card-foreground"
                        >
                            Resumen de compra
                        </h2>
                        <dl class="grid gap-2 text-sm">
                            <div class="flex items-center justify-between">
                                <dt class="text-muted-foreground">Subtotal</dt>
                                <dd class="text-card-foreground">
                                    {{ formatCurrency(subtotalNumeric) }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="text-muted-foreground">
                                    Descuentos
                                </dt>
                                <dd class="text-card-foreground">
                                    {{
                                        discountNumeric > 0
                                            ? `- ${formatCurrency(discountNumeric)}`
                                            : formatCurrency(0)
                                    }}
                                </dd>
                            </div>
                            <div
                                class="flex items-center justify-between border-t border-border pt-2"
                            >
                                <dt class="font-semibold text-card-foreground">
                                    Total
                                </dt>
                                <dd
                                    class="text-lg font-bold text-card-foreground"
                                >
                                    {{ formatCurrency(totalNumeric) }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section
                        class="rounded-lg border border-border bg-card p-5"
                    >
                        <h2
                            class="mb-4 text-base font-semibold text-card-foreground"
                        >
                            Detalles del pago
                        </h2>

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
                                    <span
                                        class="text-sm font-medium text-card-foreground"
                                    >
                                        Pago total
                                    </span>
                                    <p class="text-xs text-muted-foreground">
                                        {{ formatCurrency(totalNumeric) }}
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
                                    <span
                                        class="text-sm font-medium text-card-foreground"
                                    >
                                        Valor mínimo de reserva
                                    </span>
                                    <p
                                        class="mt-0.5 text-xs text-muted-foreground"
                                    >
                                        Paga al menos el
                                        {{ PARTIAL_PAYMENT_PERCENT * 100 }}%
                                        para asegurar tu reserva. El saldo
                                        restante podrás pagarlo antes de la
                                        fecha de la actividad.
                                    </p>
                                </div>
                            </label>
                        </div>

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
                                    :max="totalNumeric"
                                    class="mt-1"
                                />
                            </div>
                            <div
                                class="flex items-center justify-between rounded-md bg-muted/50 px-3 py-2 text-sm"
                            >
                                <span class="text-muted-foreground">
                                    Saldo pendiente
                                </span>
                                <span
                                    class="font-semibold text-card-foreground"
                                >
                                    {{ formatCurrency(pendingAfterPartial) }}
                                </span>
                            </div>
                        </div>

                        <Button
                            class="mt-5 w-full"
                            size="lg"
                            :disabled="processing"
                            @click="handlePay"
                        >
                            {{
                                processing
                                    ? 'Procesando...'
                                    : `Pagar ${formatCurrency(amountToPay)}`
                            }}
                        </Button>
                    </section>
                </div>
            </div>
        </div>

        <!-- Fallback -->
        <div v-else class="container mx-auto max-w-2xl px-4 py-16">
            <div
                class="rounded-lg border border-border bg-card px-6 py-12 text-center"
            >
                <p class="text-sm text-muted-foreground">
                    Estado de la reserva:
                    <strong>{{ formatBookingStatus(booking.status) }}</strong>
                </p>
            </div>
        </div>
    </div>
</template>
