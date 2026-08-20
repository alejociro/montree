<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Calendar, CheckCircle, MapPin, X, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { store as storePayment } from '@/actions/App/Http/Controllers/Api/V1/PaymentController';
import BookingTravelersSection from '@/components/organisms/BookingTravelersSection.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useApi } from '@/composables/useApi';
import { useTranslations } from '@/composables/useTranslations';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { formatBookingStatus, intlLocale } from '@/lib/format';
import type { BookingTraveler } from '@/types/booking';

const { t } = useTranslations();

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
    adults_count: number;
    minors_count: number;
    travelers: BookingTraveler[];
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

const props = defineProps<{
    booking: BookingProp;
    require_traveler_details: boolean;
    new_account?: boolean;
}>();

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
    return new Intl.NumberFormat(intlLocale(), {
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

    return new Intl.DateTimeFormat(intlLocale(), {
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
                toast.success(t('Pago procesado correctamente.'));
                router.reload({ only: ['booking'] });
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                toast.error(firstError ?? t('No se pudo procesar el pago.'));
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
    <Head :title="$t('Reserva :tour', { tour: booking.tour.name })" />

    <div class="bg-background">
        <!-- Success State -->
        <div v-if="isSuccess">
            <!-- Hero -->
            <div
                class="relative flex h-60 items-center justify-center bg-cover bg-center"
                :style="{ backgroundImage: `url(${heroImage})` }"
            >
                <div class="absolute inset-0 bg-brand-ink/60"></div>
                <div class="relative space-y-2 px-4 text-center text-white">
                    <CheckCircle class="mx-auto size-10" />
                    <h1 class="text-3xl font-bold">
                        {{ $t('¡Tu reserva ha sido aprobada!') }}
                    </h1>
                    <p class="text-sm text-white/90">
                        {{ $t('Ahora alegra con tu grupo de tu reserva.') }}
                    </p>
                </div>
            </div>

            <div class="container mx-auto max-w-4xl px-4 py-8">
                <!-- New account notice -->
                <div
                    v-if="new_account"
                    class="mb-6 flex items-start gap-3 rounded-lg border border-primary/30 bg-primary/10 p-4"
                >
                    <span class="mt-0.5 text-lg">✉️</span>
                    <div class="text-sm">
                        <p class="font-medium text-foreground">
                            {{ $t('¡Te creamos una cuenta!') }}
                        </p>
                        <p class="mt-0.5 text-muted-foreground">
                            {{ $t('Enviamos un correo a') }}
                            <strong>{{
                                booking.contact_snapshot?.email
                            }}</strong>
                            {{
                                $t(
                                    'con un enlace para configurar tu contraseña y acceder a tus reservas cuando quieras.',
                                )
                            }}
                        </p>
                    </div>
                </div>

                <p class="mb-6 text-sm text-muted-foreground">
                    {{
                        $t(
                            'Puedes gestionar el pago retentor y consultar los detalles de tu actividad en Mis viajes.',
                        )
                    }}
                </p>

                <!-- Date + location summary -->
                <div class="my-6 grid grid-cols-3 gap-4">
                    <div
                        class="space-y-1 rounded-lg border border-border bg-card p-4"
                    >
                        <Calendar class="size-5 text-primary" />
                        <p class="text-xs text-muted-foreground">
                            {{ $t('Fecha del viaje') }}
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
                            {{ $t('Fecha de regreso') }}
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
                            {{ $t('Punto de encuentro') }}
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
                            {{ $t('Detalles de la reserva') }}
                        </h2>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">
                                    {{ $t('Quién va') }}
                                </dt>
                                <dd class="font-medium text-foreground">
                                    {{ booking.travelers_count }} personas
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">
                                    {{ $t('ID de reserva') }}
                                </dt>
                                <dd class="font-medium text-foreground">
                                    {{ booking.booking_number }}
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">
                                    {{ $t('ID de pago') }}
                                </dt>
                                <dd class="font-medium text-foreground">—</dd>
                            </div>
                        </dl>
                        <button
                            type="button"
                            class="text-sm text-primary underline underline-offset-2"
                        >
                            {{ $t('Agregar al calendario') }}
                        </button>
                        <div class="border-t border-border pt-3">
                            <p class="text-xs text-muted-foreground">
                                {{ $t('Política de cancelación') }}
                            </p>
                            <p class="text-sm text-foreground">
                                {{ $t('Consulta detalles de políticas aquí') }}
                            </p>
                        </div>
                    </section>

                    <section
                        class="space-y-3 rounded-lg border border-border bg-card p-5"
                    >
                        <h2 class="text-base font-semibold text-foreground">
                            {{ $t('Punto de encuentro') }}
                        </h2>
                        <p class="text-sm text-foreground">
                            {{ meetingPoint }}
                        </p>
                        <div
                            class="flex h-40 items-center justify-center rounded-md bg-muted text-sm text-muted-foreground"
                        >
                            {{ $t('Mapa de encuentro') }}
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{
                                $t('Dirección: :address', {
                                    address: meetingPoint,
                                })
                            }}
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
                            {{ $t('Información de pago') }}
                        </h2>
                        <div class="flex justify-between text-sm">
                            <span class="text-muted-foreground">{{
                                $t('Subtotal')
                            }}</span>
                            <span class="text-foreground">
                                {{ formatCurrency(subtotalNumeric) }}
                            </span>
                        </div>
                        <div
                            v-if="discountNumeric > 0"
                            class="flex justify-between text-sm"
                        >
                            <span class="text-muted-foreground">{{
                                $t('Descuento')
                            }}</span>
                            <span class="text-foreground">
                                - {{ formatCurrency(discountNumeric) }}
                            </span>
                        </div>
                        <div
                            class="flex justify-between border-t border-border pt-2 text-sm"
                        >
                            <span class="font-semibold text-foreground">
                                {{ $t('Total') }}
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
                            {{ $t('Abonos') }}
                        </h2>
                        <div class="flex justify-between text-sm">
                            <span class="text-muted-foreground">
                                {{ $t('Valor abono') }}
                            </span>
                            <span class="text-foreground">
                                {{ formatCurrency(paidNumeric) }}
                            </span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-muted-foreground">
                                {{ $t('Saldo pendiente') }}
                            </span>
                            <span class="font-medium text-foreground">
                                {{ formatCurrency(pendingBalance) }}
                            </span>
                        </div>
                    </section>
                </div>

                <!-- Travelers -->
                <div class="mt-6">
                    <BookingTravelersSection
                        :booking-number="booking.booking_number"
                        :adults-count="booking.adults_count"
                        :minors-count="booking.minors_count"
                        :travelers="booking.travelers"
                        :required="require_traveler_details"
                        :contact="booking.contact_snapshot"
                    />
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
                    {{
                        booking.status === 'expired'
                            ? $t('Reserva expirada')
                            : $t('Reserva cancelada')
                    }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    <strong>{{ booking.booking_number }}</strong>
                    {{
                        booking.status === 'expired'
                            ? $t(
                                  'La reserva ha expirado. Por favor, crea una nueva reserva si deseas continuar.',
                              )
                            : $t(
                                  'La reserva fue cancelada. Por favor, crea una nueva reserva si deseas continuar.',
                              )
                    }}
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
                    {{ $t('Resumen de pago') }}
                </h1>
                <button
                    type="button"
                    class="flex size-9 items-center justify-center rounded-full text-muted-foreground transition hover:bg-muted hover:text-foreground"
                    :aria-label="$t('Cerrar')"
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
                            {{ $t('Información de la actividad') }}
                        </h2>
                        <dl class="grid gap-3 text-sm">
                            <div class="flex items-center justify-between">
                                <dt class="text-muted-foreground">
                                    {{ $t('Actividad') }}
                                </dt>
                                <dd class="font-medium text-card-foreground">
                                    {{ booking.tour.name }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="text-muted-foreground">
                                    {{ $t('Fecha') }}
                                </dt>
                                <dd class="text-card-foreground">
                                    {{
                                        formatDate(booking.tour_date.starts_at)
                                    }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="text-muted-foreground">
                                    {{ $t('Viajeros') }}
                                </dt>
                                <dd class="text-card-foreground">
                                    {{
                                        $tc(
                                            ':count persona|:count personas',
                                            booking.travelers_count,
                                        )
                                    }}
                                </dd>
                            </div>
                            <div
                                class="flex items-center justify-between border-t border-border pt-3"
                            >
                                <dt class="text-muted-foreground">
                                    {{ $t('Precio total') }}
                                </dt>
                                <dd class="text-lg font-bold text-primary">
                                    {{ formatCurrency(totalNumeric) }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <BookingTravelersSection
                        :booking-number="booking.booking_number"
                        :adults-count="booking.adults_count"
                        :minors-count="booking.minors_count"
                        :travelers="booking.travelers"
                        :required="require_traveler_details"
                        :contact="booking.contact_snapshot"
                    />
                </div>

                <!-- RIGHT -->
                <div class="space-y-6 lg:col-span-2">
                    <section
                        class="rounded-lg border border-border bg-card p-5"
                    >
                        <h2
                            class="mb-4 text-base font-semibold text-card-foreground"
                        >
                            {{ $t('Resumen de compra') }}
                        </h2>
                        <dl class="grid gap-2 text-sm">
                            <div class="flex items-center justify-between">
                                <dt class="text-muted-foreground">
                                    {{ $t('Subtotal') }}
                                </dt>
                                <dd class="text-card-foreground">
                                    {{ formatCurrency(subtotalNumeric) }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="text-muted-foreground">
                                    {{ $t('Descuentos') }}
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
                                    {{ $t('Total') }}
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
                            {{ $t('Detalles del pago') }}
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
                                        {{ $t('Pago total') }}
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
                                        {{ $t('Valor mínimo de reserva') }}
                                    </span>
                                    <p
                                        class="mt-0.5 text-xs text-muted-foreground"
                                    >
                                        {{
                                            $t(
                                                'Paga al menos el :percent% para asegurar tu reserva. El saldo restante podrás pagarlo antes de la fecha de la actividad.',
                                                {
                                                    percent:
                                                        PARTIAL_PAYMENT_PERCENT *
                                                        100,
                                                },
                                            )
                                        }}
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
                                    {{
                                        $t('Monto a pagar (mín. :amount)', {
                                            amount: formatCurrency(
                                                minPartialAmount,
                                            ),
                                        })
                                    }}
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
                                    {{ $t('Saldo pendiente') }}
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
                                    ? $t('Procesando...')
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
                    {{ $t('Estado de la reserva:') }}
                    <strong>{{ formatBookingStatus(booking.status) }}</strong>
                </p>
            </div>
        </div>
    </div>
</template>
