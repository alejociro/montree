<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Clock3, Gauge, MapPin } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { store as storeBooking } from '@/actions/App/Http/Controllers/Api/V1/BookingController';
import { show as showBooking } from '@/actions/App/Http/Controllers/BookingPagesController';
import { store as startPayment } from '@/actions/App/Http/Controllers/PaymentCheckoutController';
import { show as showTour } from '@/actions/App/Http/Controllers/PublicTourPageController';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import CounterStepper from '@/components/molecules/CounterStepper.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useApi } from '@/composables/useApi';
import { useTranslations } from '@/composables/useTranslations';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { formatTourDate, formatCurrency as money } from '@/lib/format';
import { terms as termsRoute } from '@/routes/policies';
import type { TourDifficulty } from '@/types/tour';

const { t } = useTranslations();

defineOptions({ layout: PublicLayout });

type Tour = {
    id: number;
    slug: string;
    name: string;
    cover_image_url: string | null;
    meeting_point: string | null;
    duration_hours: number | null;
    difficulty: TourDifficulty;
    default_capacity: number | null;
    category: { id: number; name: string; slug: string } | null;
};

type TourDate = {
    id: number;
    starts_at: string;
    ends_at: string | null;
    effective_price: string;
    available_seats: number;
    currency: string;
    min_payment_pct: number;
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

/**
 * Piso y techo del pago que devuelve la reserva ya creada. Son los mismos que
 * valida el Form Request: el monto se recorta contra estos y no contra la
 * aritmética del formulario, que puede diferir un centavo al redondear.
 */
type BookingCreateResponse = {
    data?: {
        booking_number?: string;
        due_amount?: string;
        min_payment_amount?: string;
        min_payment_pct?: number;
    };
};

type PaymentChoice = 'full' | 'partial';

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
const errors = reactive<Record<string, string>>({});

const paymentChoice = ref<PaymentChoice>('full');
// El componente `Input` no declara `modelModifiers`, así que `.number` no se
// aplica y el valor llega como string. Se guarda tal cual y se convierte al usarlo.
const partialAmountInput = ref('');

/**
 * El campo es de texto con `inputmode="decimal"`: en móvil abre el teclado
 * numérico y, a diferencia de `type="number"`, no dibuja flechas, no cambia de
 * valor al hacer scroll encima ni muestra el monto con la coma del idioma.
 * A cambio hay que filtrar lo que se escribe: se deja en dígitos con un solo
 * punto decimal, aceptando la coma como separador para quien la teclee.
 */
watch(partialAmountInput, (value) => {
    const [whole, ...rest] = value
        .replace(',', '.')
        .replace(/[^\d.]/g, '')
        .split('.');
    const clean =
        rest.length > 0 ? `${whole}.${rest.join('').slice(0, 2)}` : whole;

    if (clean !== value) {
        partialAmountInput.value = clean;
    }
});

const seatCap = computed(() =>
    Math.min(MAX_TOTAL_TRAVELERS, props.tourDate.available_seats),
);

const hasSeats = computed(() => props.tourDate.available_seats > 0);

const totalTravelers = computed(() => adultsCount.value + minorsCount.value);

const adultsMax = computed(() =>
    Math.max(1, seatCap.value - minorsCount.value),
);

const minorsMax = computed(() =>
    Math.max(0, seatCap.value - adultsCount.value),
);

const unitPrice = computed(() => Number(props.tourDate.effective_price));

const totalAmount = computed(() => unitPrice.value * totalTravelers.value);

const minPaymentAmount = computed(
    () => (totalAmount.value * props.tourDate.min_payment_pct) / 100,
);

const amountToPay = computed(() => {
    if (paymentChoice.value === 'full') {
        return totalAmount.value;
    }

    const requested = Number(partialAmountInput.value);

    if (!Number.isFinite(requested) || requested <= 0) {
        return minPaymentAmount.value;
    }

    return Math.min(
        totalAmount.value,
        Math.max(minPaymentAmount.value, requested),
    );
});

const balanceAfterPayment = computed(() =>
    Math.max(0, totalAmount.value - amountToPay.value),
);

function formatMoney(amount: number): string {
    return money(amount, props.tourDate.currency);
}

const difficultyLabel = computed(() => {
    const map: Record<string, string> = {
        easy: t('Fácil'),
        moderate: t('Moderado'),
        hard: t('Difícil'),
        extreme: t('Extremo'),
    };

    return map[props.tour.difficulty] ?? props.tour.difficulty;
});

const scheduleLabel = computed(() => {
    const date = formatTourDate(props.tourDate.starts_at);

    if (props.tour.duration_hours === null) {
        return date;
    }

    return t(':date · duración :count h', {
        date,
        count: props.tour.duration_hours,
    });
});

const logisticsLabel = computed(() => {
    if (props.tour.default_capacity === null) {
        return t('Dificultad :level', { level: difficultyLabel.value });
    }

    return t('Dificultad :level · grupo máx. :count', {
        level: difficultyLabel.value,
        count: props.tour.default_capacity,
    });
});

function clearErrors(): void {
    for (const key of Object.keys(errors)) {
        delete errors[key];
    }
}

function validateLocally(): boolean {
    clearErrors();

    if (props.prefill === null) {
        if (!personal.email.trim()) {
            errors.email = t('El correo electrónico es obligatorio.');
        }

        if (personal.email !== personal.email_confirmation) {
            errors.email_confirmation = t(
                'Los correos electrónicos no coinciden.',
            );
        }

        if (!personal.full_name.trim()) {
            errors.full_name = t('Los nombres y apellidos son obligatorios.');
        }

        if (!personal.phone.trim()) {
            errors.phone = t('El número celular es obligatorio.');
        }
    }

    if (adultsCount.value < 1) {
        errors.adults_count = t('Debe viajar al menos un adulto.');
    }

    if (totalTravelers.value > seatCap.value) {
        errors.adults_count = t(
            'No hay suficientes cupos disponibles para esa cantidad de viajeros.',
        );
    }

    if (!emergency.name.trim()) {
        errors.emergency_contact_name = t(
            'El contacto de emergencia es obligatorio.',
        );
    }

    if (!emergency.phone.trim()) {
        errors.emergency_contact_phone = t(
            'El contacto de emergencia es obligatorio.',
        );
    }

    if (!acceptedTerms.value) {
        errors.terms = t('Debes aceptar los términos y condiciones.');
    }

    return Object.keys(errors).length === 0;
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

/**
 * El monto viaja en unidades enteras porque los importes se muestran sin
 * decimales: prellenar el mínimo exacto (55,64) debajo de un rótulo que dice
 * «mín $56» se lee como un error. Redondear hacia arriba nunca baja del piso
 * que valida el servidor.
 */
function wholeUnits(amount: number): string {
    return String(Math.ceil(amount));
}

function selectPaymentChoice(choice: PaymentChoice): void {
    paymentChoice.value = choice;

    if (choice === 'partial' && partialAmountInput.value === '') {
        partialAmountInput.value = wholeUnits(minPaymentAmount.value);
    }
}

/** Los límites se mueven con la cantidad de viajeros; el monto escrito los sigue. */
watch(totalAmount, () => {
    if (paymentChoice.value !== 'partial' || partialAmountInput.value === '') {
        return;
    }

    partialAmountInput.value = wholeUnits(amountToPay.value);
});

/**
 * Monto del abono recortado contra los números que devolvió el servidor. Si el
 * formulario mandara los suyos, un centavo de diferencia sería un 422.
 */
function resolvePartialAmount(data: BookingCreateResponse['data']): number {
    const min = Number(data?.min_payment_amount ?? minPaymentAmount.value);
    const max = Number(data?.due_amount ?? totalAmount.value);
    const requested = Number(partialAmountInput.value);

    if (!Number.isFinite(requested) || requested <= 0) {
        return min;
    }

    return Math.min(max, Math.max(min, requested));
}

// La reserva ya existe: este POST abre la sesion en PlacetoPay y el servidor
// responde con la redireccion externa al checkout, que Inertia sigue sola. Si
// algo falla, el usuario cae en el detalle de la reserva y puede reintentar.
function payBooking(
    bookingNumber: string,
    data: BookingCreateResponse['data'],
): void {
    const payload =
        paymentChoice.value === 'full'
            ? { type: 'full' }
            : { type: 'partial', amount: resolvePartialAmount(data) };

    router.post(startPayment(bookingNumber).url, payload, {
        onError: (validationErrors) => {
            const firstError = Object.values(validationErrors)[0];
            toast.error(firstError ?? t('No pudimos iniciar el pago.'));
            router.visit(showBooking(bookingNumber).url);
        },
        onFinish: () => {
            submitting.value = false;
        },
    });
}

async function submit(): Promise<void> {
    if (submitting.value || !hasSeats.value) {
        return;
    }

    if (!validateLocally()) {
        toast.error(
            Object.values(errors)[0] ?? t('Revisa los datos del formulario.'),
        );

        return;
    }

    submitting.value = true;

    await api.post<BookingCreateResponse>(storeBooking().url, buildPayload(), {
        onSuccess: (response) => {
            const bookingNumber = response?.data?.booking_number ?? null;

            if (bookingNumber === null) {
                submitting.value = false;
                toast.error(t('No pudimos crear la reserva.'));

                return;
            }

            payBooking(bookingNumber, response?.data);
        },
        onError: (serverErrors) => {
            submitting.value = false;
            Object.assign(errors, serverErrors);
            const firstError = Object.values(serverErrors)[0];
            toast.error(firstError ?? t('No pudimos crear la reserva.'));
        },
    });
}
</script>

<template>
    <Head :title="$t('Reservar :tour', { tour: tour.name })" />

    <div class="bg-background">
        <div
            class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10"
        >
            <Link
                :href="showTour(tour.slug).url"
                class="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-muted-foreground transition hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <span aria-hidden="true">&larr;</span>
                {{ $t('Volver al tour') }}
            </Link>

            <form
                class="grid items-start gap-8 lg:grid-cols-[minmax(0,1.55fr)_minmax(0,1fr)] lg:gap-10"
                novalidate
                @submit.prevent="submit"
            >
                <div class="min-w-0">
                    <h1
                        class="text-3xl leading-tight font-extrabold tracking-tight text-foreground sm:text-4xl"
                    >
                        {{ $t('Confirma tu reserva') }}
                    </h1>
                    <p
                        class="mt-2 max-w-[52ch] text-sm text-muted-foreground sm:text-[15px]"
                    >
                        {{
                            $t(
                                'Faltan un par de datos. Guardamos tu lugar en esta salida mientras completas el formulario.',
                            )
                        }}
                    </p>

                    <div
                        v-if="hasSeats"
                        class="mt-5 flex items-center gap-2.5 rounded-xl bg-primary/10 px-4 py-3 text-[13px] font-semibold text-primary"
                    >
                        <span
                            class="size-[7px] shrink-0 rounded-full bg-primary"
                            aria-hidden="true"
                        />
                        <span>
                            {{
                                $t('Cupos disponibles: :count', {
                                    count: tourDate.available_seats,
                                })
                            }}
                        </span>
                    </div>

                    <div
                        v-else
                        class="mt-5 rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-[13px] font-semibold text-destructive"
                        role="status"
                    >
                        {{
                            $t(
                                'Esta salida ya no tiene cupos disponibles. Elegí otra fecha.',
                            )
                        }}
                    </div>

                    <div class="mt-8 space-y-4">
                        <!-- 1. Quién reserva -->
                        <section
                            class="rounded-[20px] border border-border bg-card p-6 sm:p-7"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="flex size-[26px] shrink-0 items-center justify-center rounded-full bg-foreground text-[13px] font-bold text-background"
                                    aria-hidden="true"
                                >
                                    1
                                </span>
                                <h2
                                    class="text-lg font-bold tracking-tight text-card-foreground"
                                >
                                    {{ $t('Quién reserva') }}
                                </h2>
                            </div>
                            <p
                                class="mt-1.5 ml-[38px] text-[13px] text-muted-foreground"
                            >
                                {{
                                    prefill === null
                                        ? $t(
                                              'Creamos tu cuenta con este correo para que gestiones la reserva.',
                                          )
                                        : $t(
                                              'Usaremos los datos de tu cuenta para esta reserva.',
                                          )
                                }}
                            </p>

                            <div
                                v-if="prefill !== null"
                                class="mt-5 space-y-1 rounded-[14px] border border-border bg-background px-4 py-3.5 text-sm"
                            >
                                <p class="font-semibold text-foreground">
                                    {{ prefill.full_name }}
                                </p>
                                <p class="text-muted-foreground">
                                    {{ prefill.email }}
                                </p>
                                <p
                                    v-if="prefill.phone"
                                    class="text-muted-foreground"
                                >
                                    {{ prefill.phone }}
                                </p>
                            </div>

                            <div
                                v-else
                                class="mt-5 grid gap-4 sm:grid-cols-2 sm:gap-x-[18px]"
                            >
                                <div class="space-y-1.5">
                                    <Label for="email">
                                        {{ $t('Correo electrónico *') }}
                                    </Label>
                                    <Input
                                        id="email"
                                        v-model="personal.email"
                                        type="email"
                                        class="h-11 rounded-[11px]"
                                        placeholder="tu@correo.com"
                                        autocomplete="email"
                                        :aria-invalid="
                                            errors.email ? true : undefined
                                        "
                                        :aria-describedby="
                                            errors.email
                                                ? 'email-error'
                                                : undefined
                                        "
                                    />
                                    <p
                                        v-if="errors.email"
                                        id="email-error"
                                        class="text-xs text-destructive"
                                    >
                                        {{ errors.email }}
                                    </p>
                                </div>
                                <div class="space-y-1.5">
                                    <Label for="email_confirmation">
                                        {{ $t('Confirmar correo *') }}
                                    </Label>
                                    <Input
                                        id="email_confirmation"
                                        v-model="personal.email_confirmation"
                                        type="email"
                                        class="h-11 rounded-[11px]"
                                        placeholder="tu@correo.com"
                                        autocomplete="email"
                                        :aria-invalid="
                                            errors.email_confirmation
                                                ? true
                                                : undefined
                                        "
                                        :aria-describedby="
                                            errors.email_confirmation
                                                ? 'email_confirmation-error'
                                                : undefined
                                        "
                                    />
                                    <p
                                        v-if="errors.email_confirmation"
                                        id="email_confirmation-error"
                                        class="text-xs text-destructive"
                                    >
                                        {{ errors.email_confirmation }}
                                    </p>
                                </div>
                                <div class="space-y-1.5">
                                    <Label for="full_name">
                                        {{ $t('Nombres y apellidos *') }}
                                    </Label>
                                    <Input
                                        id="full_name"
                                        v-model="personal.full_name"
                                        type="text"
                                        class="h-11 rounded-[11px]"
                                        :placeholder="
                                            $t('Como aparece en tu documento')
                                        "
                                        autocomplete="name"
                                        :aria-invalid="
                                            errors.full_name ? true : undefined
                                        "
                                        :aria-describedby="
                                            errors.full_name
                                                ? 'full_name-error'
                                                : undefined
                                        "
                                    />
                                    <p
                                        v-if="errors.full_name"
                                        id="full_name-error"
                                        class="text-xs text-destructive"
                                    >
                                        {{ errors.full_name }}
                                    </p>
                                </div>
                                <div class="space-y-1.5">
                                    <Label for="phone">
                                        {{ $t('Número celular *') }}
                                    </Label>
                                    <Input
                                        id="phone"
                                        v-model="personal.phone"
                                        type="tel"
                                        class="h-11 rounded-[11px]"
                                        placeholder="+57 300 000 0000"
                                        autocomplete="tel"
                                        :aria-invalid="
                                            errors.phone ? true : undefined
                                        "
                                        :aria-describedby="
                                            errors.phone
                                                ? 'phone-error'
                                                : undefined
                                        "
                                    />
                                    <p
                                        v-if="errors.phone"
                                        id="phone-error"
                                        class="text-xs text-destructive"
                                    >
                                        {{ errors.phone }}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <!-- 2. Viajeros -->
                        <section
                            class="rounded-[20px] border border-border bg-card p-6 sm:p-7"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="flex size-[26px] shrink-0 items-center justify-center rounded-full bg-foreground text-[13px] font-bold text-background"
                                    aria-hidden="true"
                                >
                                    2
                                </span>
                                <h2
                                    class="text-lg font-bold tracking-tight text-card-foreground"
                                >
                                    {{ $t('Viajeros') }}
                                </h2>
                            </div>
                            <p
                                class="mt-1.5 ml-[38px] text-[13px] text-muted-foreground"
                            >
                                {{
                                    $t(
                                        'Los datos de cada viajero se completan después, desde el detalle de tu reserva.',
                                    )
                                }}
                            </p>

                            <div class="mt-5 space-y-2.5">
                                <CounterStepper
                                    v-model="adultsCount"
                                    :label="$t('Adultos')"
                                    :description="
                                        $t('Mayores de edad · :price c/u', {
                                            price: formatMoney(unitPrice),
                                        })
                                    "
                                    :min="1"
                                    :max="adultsMax"
                                />
                                <CounterStepper
                                    v-model="minorsCount"
                                    :label="$t('Menores')"
                                    :description="
                                        $t('Menores de edad · :price c/u', {
                                            price: formatMoney(unitPrice),
                                        })
                                    "
                                    :min="0"
                                    :max="minorsMax"
                                />
                            </div>

                            <p
                                v-if="errors.adults_count"
                                class="mt-2 text-xs text-destructive"
                                role="alert"
                            >
                                {{ errors.adults_count }}
                            </p>
                        </section>

                        <!-- 3. Contacto de emergencia -->
                        <section
                            class="rounded-[20px] border border-border bg-card p-6 sm:p-7"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="flex size-[26px] shrink-0 items-center justify-center rounded-full bg-foreground text-[13px] font-bold text-background"
                                    aria-hidden="true"
                                >
                                    3
                                </span>
                                <h2
                                    class="text-lg font-bold tracking-tight text-card-foreground"
                                >
                                    {{ $t('Contacto de emergencia') }}
                                </h2>
                            </div>
                            <p
                                class="mt-1.5 ml-[38px] text-[13px] text-muted-foreground"
                            >
                                {{
                                    $t(
                                        'Alguien que no viaje contigo, por si el guía necesita comunicarse.',
                                    )
                                }}
                            </p>

                            <div
                                class="mt-5 grid gap-4 sm:grid-cols-2 sm:gap-x-[18px]"
                            >
                                <div class="space-y-1.5">
                                    <Label for="emergency_name">
                                        {{ $t('Nombres y apellidos *') }}
                                    </Label>
                                    <Input
                                        id="emergency_name"
                                        v-model="emergency.name"
                                        type="text"
                                        class="h-11 rounded-[11px]"
                                        :aria-invalid="
                                            errors.emergency_contact_name
                                                ? true
                                                : undefined
                                        "
                                        :aria-describedby="
                                            errors.emergency_contact_name
                                                ? 'emergency_name-error'
                                                : undefined
                                        "
                                    />
                                    <p
                                        v-if="errors.emergency_contact_name"
                                        id="emergency_name-error"
                                        class="text-xs text-destructive"
                                    >
                                        {{ errors.emergency_contact_name }}
                                    </p>
                                </div>
                                <div class="space-y-1.5">
                                    <Label for="emergency_phone">
                                        {{ $t('Número celular *') }}
                                    </Label>
                                    <Input
                                        id="emergency_phone"
                                        v-model="emergency.phone"
                                        type="tel"
                                        class="h-11 rounded-[11px]"
                                        placeholder="+57 300 000 0000"
                                        :aria-invalid="
                                            errors.emergency_contact_phone
                                                ? true
                                                : undefined
                                        "
                                        :aria-describedby="
                                            errors.emergency_contact_phone
                                                ? 'emergency_phone-error'
                                                : undefined
                                        "
                                    />
                                    <p
                                        v-if="errors.emergency_contact_phone"
                                        id="emergency_phone-error"
                                        class="text-xs text-destructive"
                                    >
                                        {{ errors.emergency_contact_phone }}
                                    </p>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>

                <!-- Resumen -->
                <aside class="min-w-0 lg:sticky lg:top-24">
                    <div
                        class="overflow-hidden rounded-[20px] border border-border bg-card"
                    >
                        <div class="relative h-[168px] bg-muted">
                            <img
                                v-if="tour.cover_image_url"
                                :src="tour.cover_image_url"
                                :alt="tour.name"
                                class="size-full object-cover"
                            />
                            <div
                                v-else
                                class="flex size-full items-end p-3.5"
                                aria-hidden="true"
                            >
                                <MonoLabel
                                    class="rounded-md bg-card/85 px-2 py-1.5"
                                >
                                    {{ $t('Foto del tour') }}
                                </MonoLabel>
                            </div>
                        </div>

                        <div class="p-5 sm:p-6">
                            <MonoLabel>
                                {{
                                    tour.category === null
                                        ? $t('Tu salida')
                                        : $t(':category · Tu salida', {
                                              category: tour.category.name,
                                          })
                                }}
                            </MonoLabel>

                            <h2
                                class="mt-2 text-2xl leading-tight font-extrabold tracking-tight text-card-foreground"
                            >
                                {{ tour.name }}
                            </h2>

                            <dl
                                class="mt-4 space-y-2.5 border-b border-border pb-4 text-[13.5px] text-card-foreground"
                            >
                                <div class="flex gap-2.5">
                                    <dt class="shrink-0 text-muted-foreground">
                                        <Clock3 class="size-4" />
                                        <span class="sr-only">
                                            {{ $t('Fecha y hora') }}
                                        </span>
                                    </dt>
                                    <dd>{{ scheduleLabel }}</dd>
                                </div>
                                <div
                                    v-if="tour.meeting_point"
                                    class="flex gap-2.5"
                                >
                                    <dt class="shrink-0 text-muted-foreground">
                                        <MapPin class="size-4" />
                                        <span class="sr-only">
                                            {{ $t('Punto de encuentro') }}
                                        </span>
                                    </dt>
                                    <dd>{{ tour.meeting_point }}</dd>
                                </div>
                                <div class="flex gap-2.5">
                                    <dt class="shrink-0 text-muted-foreground">
                                        <Gauge class="size-4" />
                                        <span class="sr-only">
                                            {{ $t('Dificultad') }}
                                        </span>
                                    </dt>
                                    <dd>{{ logisticsLabel }}</dd>
                                </div>
                            </dl>

                            <dl
                                class="space-y-2 border-b border-border py-4 text-[13.5px] text-card-foreground"
                            >
                                <div class="flex justify-between gap-3">
                                    <dt>
                                        {{
                                            $tc(
                                                ':count adulto|:count adultos',
                                                adultsCount,
                                            )
                                        }}
                                    </dt>
                                    <dd class="font-semibold">
                                        {{
                                            formatMoney(unitPrice * adultsCount)
                                        }}
                                    </dd>
                                </div>
                                <div
                                    v-if="minorsCount > 0"
                                    class="flex justify-between gap-3"
                                >
                                    <dt>
                                        {{
                                            $tc(
                                                ':count menor|:count menores',
                                                minorsCount,
                                            )
                                        }}
                                    </dt>
                                    <dd class="font-semibold">
                                        {{
                                            formatMoney(unitPrice * minorsCount)
                                        }}
                                    </dd>
                                </div>
                            </dl>

                            <div
                                class="flex items-baseline justify-between gap-3 py-4"
                            >
                                <div>
                                    <p
                                        class="text-[13px] font-bold text-card-foreground"
                                    >
                                        {{ $t('Total') }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{
                                            $tc(
                                                ':count viajero|:count viajeros',
                                                totalTravelers,
                                            )
                                        }}
                                    </p>
                                </div>
                                <p
                                    class="text-[28px] font-extrabold tracking-tight text-card-foreground"
                                >
                                    {{ formatMoney(totalAmount) }}
                                </p>
                            </div>

                            <!-- Monto a pagar ahora -->
                            <fieldset
                                class="rounded-[14px] border border-border bg-background p-4"
                            >
                                <!--
                                    `float-left w-full` saca al legend del hueco
                                    que el navegador le abre en el borde: sin
                                    eso el título se monta sobre el marco y se
                                    sale de la tarjeta.
                                -->
                                <legend
                                    class="float-left w-full p-0 text-[13px] font-bold text-card-foreground"
                                >
                                    {{ $t('¿Cuánto querés pagar ahora?') }}
                                </legend>

                                <div class="clear-both mt-3 space-y-2">
                                    <label
                                        class="flex cursor-pointer items-center gap-3 rounded-[11px] border px-3.5 py-2.5 transition"
                                        :class="
                                            paymentChoice === 'full'
                                                ? 'border-primary bg-primary/5'
                                                : 'border-border hover:border-muted-foreground/30'
                                        "
                                    >
                                        <input
                                            type="radio"
                                            name="payment_choice"
                                            value="full"
                                            :checked="paymentChoice === 'full'"
                                            class="size-4 accent-primary"
                                            @change="
                                                selectPaymentChoice('full')
                                            "
                                        />
                                        <span class="flex-1 text-[13px]">
                                            <span
                                                class="font-semibold text-card-foreground"
                                            >
                                                {{ $t('Total') }}
                                            </span>
                                        </span>
                                        <span
                                            class="text-[13px] font-semibold text-card-foreground"
                                        >
                                            {{ formatMoney(totalAmount) }}
                                        </span>
                                    </label>

                                    <label
                                        class="flex cursor-pointer items-center gap-3 rounded-[11px] border px-3.5 py-2.5 transition"
                                        :class="
                                            paymentChoice === 'partial'
                                                ? 'border-primary bg-primary/5'
                                                : 'border-border hover:border-muted-foreground/30'
                                        "
                                    >
                                        <input
                                            type="radio"
                                            name="payment_choice"
                                            value="partial"
                                            :checked="
                                                paymentChoice === 'partial'
                                            "
                                            class="size-4 accent-primary"
                                            @change="
                                                selectPaymentChoice('partial')
                                            "
                                        />
                                        <span class="flex-1 text-[13px]">
                                            <span
                                                class="font-semibold text-card-foreground"
                                            >
                                                {{
                                                    $t(
                                                        'Abono mínimo (:percent%)',
                                                        {
                                                            percent:
                                                                tourDate.min_payment_pct,
                                                        },
                                                    )
                                                }}
                                            </span>
                                        </span>
                                        <span
                                            class="text-[13px] font-semibold text-card-foreground"
                                        >
                                            {{ formatMoney(minPaymentAmount) }}
                                        </span>
                                    </label>
                                </div>

                                <div
                                    v-if="paymentChoice === 'partial'"
                                    class="mt-3 space-y-2"
                                >
                                    <Label
                                        for="partial_amount"
                                        class="text-[13px]"
                                    >
                                        {{ $t('Monto a pagar ahora') }}
                                    </Label>
                                    <Input
                                        id="partial_amount"
                                        v-model="partialAmountInput"
                                        type="text"
                                        inputmode="decimal"
                                        autocomplete="off"
                                        class="h-11 rounded-[11px]"
                                        aria-describedby="partial_amount-hint"
                                    />
                                    <p
                                        id="partial_amount-hint"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{
                                            $t('mín :min · máx :max', {
                                                min: formatMoney(
                                                    minPaymentAmount,
                                                ),
                                                max: formatMoney(totalAmount),
                                            })
                                        }}
                                    </p>
                                    <p
                                        class="flex justify-between gap-3 rounded-[11px] bg-muted/60 px-3 py-2 text-[13px]"
                                    >
                                        <span class="text-muted-foreground">
                                            {{ $t('Saldo después:') }}
                                        </span>
                                        <span
                                            class="font-semibold text-card-foreground"
                                        >
                                            {{
                                                formatMoney(balanceAfterPayment)
                                            }}
                                        </span>
                                    </p>
                                </div>
                            </fieldset>

                            <label
                                class="mt-4 flex cursor-pointer items-start gap-2.5 text-[12.5px] leading-relaxed text-muted-foreground"
                            >
                                <Checkbox
                                    class="mt-0.5"
                                    :model-value="acceptedTerms"
                                    :aria-describedby="
                                        errors.terms ? 'terms-error' : undefined
                                    "
                                    @update:model-value="
                                        acceptedTerms = Boolean($event)
                                    "
                                />
                                <span>
                                    {{ $t('Acepto los') }}
                                    <a
                                        :href="termsRoute.url()"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-primary-readable underline underline-offset-2"
                                    >
                                        {{ $t('términos y condiciones') }}</a
                                    >.
                                </span>
                            </label>

                            <p
                                v-if="errors.terms"
                                id="terms-error"
                                class="mt-1.5 text-xs text-destructive"
                            >
                                {{ errors.terms }}
                            </p>

                            <Button
                                type="submit"
                                size="lg"
                                class="mt-4 h-[52px] w-full rounded-[14px] text-[15.5px] font-bold"
                                :disabled="submitting || !hasSeats"
                            >
                                {{
                                    submitting
                                        ? $t('Procesando...')
                                        : $t('Ir al pago · :amount', {
                                              amount: formatMoney(amountToPay),
                                          })
                                }}
                            </Button>

                            <p
                                class="mt-3 text-center text-xs text-muted-foreground"
                            >
                                {{ $t('Pago seguro') }}
                            </p>
                        </div>
                    </div>
                </aside>
            </form>
        </div>
    </div>
</template>
