<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import { store as storeBooking } from '@/actions/App/Http/Controllers/Api/V1/BookingController';
import { store as storePayment } from '@/actions/App/Http/Controllers/Api/V1/PaymentController';
import CounterStepper from '@/components/molecules/CounterStepper.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useApi } from '@/composables/useApi';
import PublicLayout from '@/layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

type Tour = {
    id: number;
    slug: string;
    name: string;
    description: string;
    cover_image_url: string | null;
    meeting_point: string | null;
};

type TourDate = {
    id: number;
    starts_at: string;
    ends_at: string | null;
    effective_price: string;
    available_seats: number;
    currency: string;
};

type Prefill = {
    email: string;
    full_name: string;
    phone: string;
};

const props = defineProps<{
    tour: Tour;
    tourDate: TourDate;
    prefill: Prefill | null;
}>();

const api = useApi();

const MAX_TOTAL_TRAVELERS = 50;

type BookingCreateResponse = {
    data?: { booking_number?: string };
};

const personal = reactive({
    email: props.prefill?.email ?? '',
    email_confirmation: props.prefill?.email ?? '',
    full_name: props.prefill?.full_name ?? '',
    phone: props.prefill?.phone ?? '',
});

const adultsCount = ref(1);
const minorsCount = ref(0);

const emergency = reactive({
    name: '',
    phone: '',
});

const acceptedTerms = ref(false);
const submitting = ref(false);

const seatCap = computed(() =>
    Math.min(MAX_TOTAL_TRAVELERS, props.tourDate.available_seats),
);

const totalTravelers = computed(() => adultsCount.value + minorsCount.value);

const adultsMax = computed(() =>
    Math.max(1, seatCap.value - minorsCount.value),
);

const minorsMax = computed(() =>
    Math.max(0, seatCap.value - adultsCount.value),
);

const formattedPrice = computed(() =>
    new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: props.tourDate.currency,
        maximumFractionDigits: 0,
    }).format(Number(props.tourDate.effective_price)),
);

const formattedTotal = computed(() =>
    new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: props.tourDate.currency,
        maximumFractionDigits: 0,
    }).format(Number(props.tourDate.effective_price) * totalTravelers.value),
);

function validateLocally(): string | null {
    if (props.prefill === null) {
        if (!personal.email.trim()) {
            return 'El correo electrónico es obligatorio.';
        }

        if (personal.email !== personal.email_confirmation) {
            return 'Los correos electrónicos no coinciden.';
        }

        if (!personal.full_name.trim()) {
            return 'Los nombres y apellidos son obligatorios.';
        }

        if (!personal.phone.trim()) {
            return 'El número celular es obligatorio.';
        }
    }

    if (adultsCount.value < 1) {
        return 'Debe viajar al menos un adulto.';
    }

    if (totalTravelers.value > seatCap.value) {
        return 'No hay suficientes cupos disponibles para esa cantidad de viajeros.';
    }

    if (!emergency.name.trim() || !emergency.phone.trim()) {
        return 'El contacto de emergencia es obligatorio.';
    }

    if (!acceptedTerms.value) {
        return 'Debes aceptar los términos y condiciones.';
    }

    return null;
}

function buildPayload(): Record<string, unknown> {
    const payload: Record<string, unknown> = {
        tour_date_id: props.tourDate.id,
        adults_count: adultsCount.value,
        minors_count: minorsCount.value,
        emergency_contact_name: emergency.name,
        emergency_contact_phone: emergency.phone,
    };

    if (props.prefill === null) {
        payload.email = personal.email;
        payload.email_confirmation = personal.email_confirmation;
        payload.full_name = personal.full_name;
        payload.phone = personal.phone;
    }

    return payload;
}

async function payBooking(bookingNumber: string): Promise<void> {
    const paymentAction = storePayment(bookingNumber);

    await api.post(
        paymentAction.url,
        { type: 'full' },
        {
            onSuccess: () => {
                router.visit(`/bookings/${bookingNumber}`);
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                toast.error(firstError ?? 'No pudimos procesar el pago.');
            },
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
}

async function submit(): Promise<void> {
    if (submitting.value) {
        return;
    }

    const validationError = validateLocally();

    if (validationError !== null) {
        toast.error(validationError);

        return;
    }

    submitting.value = true;

    await api.post<BookingCreateResponse>(storeBooking().url, buildPayload(), {
        onSuccess: async (response) => {
            const bookingNumber = response?.data?.booking_number ?? null;

            if (bookingNumber === null) {
                submitting.value = false;
                toast.error('No pudimos crear la reserva.');

                return;
            }

            await payBooking(bookingNumber);
        },
        onError: (errors) => {
            submitting.value = false;
            const firstError = Object.values(errors)[0];
            toast.error(firstError ?? 'No pudimos crear la reserva.');
        },
    });
}
</script>

<template>
    <Head :title="`Reservar ${tour.name}`" />

    <div class="bg-background">
        <div class="container mx-auto max-w-2xl px-4 py-8">
            <Link
                :href="`/tours/${tour.slug}`"
                class="mb-4 inline-flex items-center gap-1 text-sm font-medium text-muted-foreground transition hover:text-foreground"
            >
                <span aria-hidden="true">&larr;</span>
                Volver
            </Link>

            <header class="mb-6 space-y-1">
                <h1 class="text-2xl font-bold text-foreground">
                    Formulario de reserva
                </h1>
                <p class="text-sm text-muted-foreground">
                    Para completar la reserva es necesario que diligencies el
                    siguiente formulario
                </p>
                <p class="pt-1 text-sm text-muted-foreground">
                    {{ tour.name }} ·
                    {{ new Date(tourDate.starts_at).toLocaleString('es-CO') }} ·
                    {{ formattedPrice }} por persona
                </p>
            </header>

            <form class="space-y-8" @submit.prevent="submit">
                <!-- Section 1: Información personal -->
                <section class="space-y-4">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex h-7 w-7 items-center justify-center rounded-full bg-foreground text-sm font-bold text-background"
                        >
                            1
                        </span>
                        <h2 class="text-lg font-semibold text-foreground">
                            Información personal
                        </h2>
                    </div>

                    <p
                        v-if="prefill === null"
                        class="rounded-md bg-primary/10 px-3 py-2 text-xs text-foreground/70"
                    >
                        Al reservar crearemos una cuenta con tu correo para que
                        puedas gestionar tus reservas y recibir actualizaciones.
                    </p>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label for="email">Correo electrónico *</Label>
                            <Input
                                id="email"
                                v-model="personal.email"
                                type="email"
                                autocomplete="email"
                                required
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="email_confirmation">
                                Confirmación de correo electrónico *
                            </Label>
                            <Input
                                id="email_confirmation"
                                v-model="personal.email_confirmation"
                                type="email"
                                autocomplete="email"
                                required
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="full_name">Nombres y apellidos *</Label>
                            <Input
                                id="full_name"
                                v-model="personal.full_name"
                                type="text"
                                placeholder="Como aparece en tu documento"
                                autocomplete="name"
                                required
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="phone">Número celular *</Label>
                            <Input
                                id="phone"
                                v-model="personal.phone"
                                type="tel"
                                placeholder="+57 300 000 0000"
                                autocomplete="tel"
                                required
                            />
                        </div>
                    </div>
                </section>

                <!-- Section 2: ¿Quiénes van a viajar? -->
                <section class="space-y-4">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex h-7 w-7 items-center justify-center rounded-full bg-foreground text-sm font-bold text-background"
                        >
                            2
                        </span>
                        <h2 class="text-lg font-semibold text-foreground">
                            ¿Cuántos van a viajar? *
                        </h2>
                    </div>

                    <div class="space-y-3">
                        <CounterStepper
                            v-model="adultsCount"
                            label="Adultos"
                            description="Mayores de edad"
                            :min="1"
                            :max="adultsMax"
                        />
                        <CounterStepper
                            v-model="minorsCount"
                            label="Menores"
                            description="Menores de edad"
                            :min="0"
                            :max="minorsMax"
                        />
                    </div>

                    <p
                        class="rounded-md bg-primary/10 px-3 py-2 text-xs text-foreground/70"
                    >
                        Los datos de cada viajero (nombre, documento, etc.) se
                        completan después de reservar, desde el detalle de tu
                        reserva.
                    </p>

                    <div
                        class="flex items-center justify-between rounded-lg border border-border bg-card px-4 py-3 text-sm"
                    >
                        <span class="text-muted-foreground">
                            {{ totalTravelers }}
                            {{ totalTravelers === 1 ? 'viajero' : 'viajeros' }}
                            × {{ formattedPrice }}
                        </span>
                        <span class="font-semibold text-foreground">
                            {{ formattedTotal }}
                        </span>
                    </div>
                </section>

                <!-- Emergency contact -->
                <section class="space-y-4">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex h-7 w-7 items-center justify-center rounded-full bg-foreground text-sm font-bold text-background"
                        >
                            3
                        </span>
                        <h2 class="text-lg font-semibold text-foreground">
                            Contacto de emergencia
                        </h2>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label for="emergency_name">
                                Nombres y apellidos *
                            </Label>
                            <Input
                                id="emergency_name"
                                v-model="emergency.name"
                                type="text"
                                required
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="emergency_phone">
                                Número celular *
                            </Label>
                            <Input
                                id="emergency_phone"
                                v-model="emergency.phone"
                                type="tel"
                                placeholder="+57 300 000 0000"
                                required
                            />
                        </div>
                    </div>
                </section>

                <!-- Footer -->
                <div class="space-y-4 border-t border-border pt-6">
                    <label
                        class="flex cursor-pointer items-center gap-2 text-sm text-foreground"
                    >
                        <Checkbox
                            :model-value="acceptedTerms"
                            @update:model-value="
                                acceptedTerms = Boolean($event)
                            "
                        />
                        <span>
                            Acepto los
                            <a
                                href="/terms"
                                target="_blank"
                                rel="noopener"
                                class="text-primary underline underline-offset-2"
                            >
                                términos y condiciones
                            </a>
                        </span>
                    </label>

                    <Button
                        type="submit"
                        size="lg"
                        class="w-full bg-foreground text-background hover:bg-foreground/90"
                        :disabled="submitting"
                    >
                        {{ submitting ? 'Procesando...' : 'Realizar pago' }}
                    </Button>
                </div>
            </form>
        </div>
    </div>
</template>
