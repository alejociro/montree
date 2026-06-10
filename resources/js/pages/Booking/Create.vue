<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronDown, Minus, Plus } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { store as storeBooking } from '@/actions/App/Http/Controllers/Api/V1/BookingController';
import { store as storePayment } from '@/actions/App/Http/Controllers/Api/V1/PaymentController';
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
    requireTravelers: boolean;
    prefill: Prefill | null;
}>();

const api = useApi();

type TravelerMode = 'complete_now' | 'share_link';

type TravelerInput = {
    full_name: string;
    phone: string;
};

type BookingCreateResponse = {
    data?: { booking_number?: string };
};

const personal = reactive({
    email: props.prefill?.email ?? '',
    email_confirmation: props.prefill?.email ?? '',
    full_name: props.prefill?.full_name ?? '',
    phone: props.prefill?.phone ?? '',
});

const soyViajero = ref(false);

const adults = ref(1);
const children = ref(0);
const babies = ref(0);
const countersOpen = ref(false);

const travelerMode = ref<TravelerMode>('share_link');

const emergency = reactive({
    name: '',
    phone: '',
});

const acceptedTerms = ref(false);
const submitting = ref(false);

const travelersCount = computed(
    () => adults.value + children.value + babies.value,
);

const minAdults = computed(() => (soyViajero.value ? 1 : 0));

const travelers = reactive<TravelerInput[]>([]);

watch(travelerMode, (mode) => {
    if (mode === 'complete_now') {
        syncTravelers();
    }
});

function syncTravelers(): void {
    const count = travelersCount.value;

    while (travelers.length < count) {
        travelers.push({ full_name: '', phone: '' });
    }

    travelers.length = count;

    if (soyViajero.value && travelers.length > 0) {
        travelers[0].full_name = personal.full_name;
        travelers[0].phone = personal.phone;
    }
}

function increment(counter: 'adults' | 'children' | 'babies'): void {
    if (counter === 'adults') {
        adults.value += 1;
    } else if (counter === 'children') {
        children.value += 1;
    } else {
        babies.value += 1;
    }

    syncTravelers();
}

function decrement(counter: 'adults' | 'children' | 'babies'): void {
    if (counter === 'adults' && adults.value > minAdults.value) {
        adults.value -= 1;
    } else if (counter === 'children' && children.value > 0) {
        children.value -= 1;
    } else if (counter === 'babies' && babies.value > 0) {
        babies.value -= 1;
    }

    syncTravelers();
}

function onSoyViajeroChange(checked: boolean): void {
    soyViajero.value = checked;

    if (checked && adults.value < 1) {
        adults.value = 1;
    }

    syncTravelers();
}

const formattedPrice = computed(() =>
    new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: props.tourDate.currency,
        maximumFractionDigits: 0,
    }).format(Number(props.tourDate.effective_price)),
);

const summaryLabel = computed(() => {
    const parts: string[] = [];
    parts.push(`${adults.value} Adulto${adults.value === 1 ? '' : 's'}`);
    parts.push(`${children.value} Niño${children.value === 1 ? '' : 's'}`);

    if (babies.value > 0) {
        parts.push(`${babies.value} Bebé${babies.value === 1 ? '' : 's'}`);
    }

    return parts.join(' - ');
});

const emergencySectionNumber = computed(() =>
    travelerMode.value === 'complete_now' ? 3 + travelersCount.value : 3,
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

    if (travelersCount.value < 1) {
        return 'Debes seleccionar al menos un viajero.';
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
        travelers_count: travelersCount.value,
        emergency_contact_name: emergency.name,
        emergency_contact_phone: emergency.phone,
        traveler_mode: travelerMode.value,
    };

    if (props.prefill === null) {
        payload.email = personal.email;
        payload.email_confirmation = personal.email_confirmation;
        payload.full_name = personal.full_name;
        payload.phone = personal.phone;
    }

    if (travelerMode.value === 'complete_now') {
        payload.travelers = travelers.map((traveler) => ({
            full_name: traveler.full_name,
            phone: traveler.phone,
        }));
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

                    <label
                        class="flex cursor-pointer items-center gap-2 text-sm text-muted-foreground"
                    >
                        <Checkbox
                            :model-value="soyViajero"
                            @update:model-value="
                                onSoyViajeroChange(Boolean($event))
                            "
                        />
                        También viajo en este grupo
                    </label>
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
                            ¿Quiénes van a viajar? *
                        </h2>
                    </div>

                    <button
                        type="button"
                        class="flex w-full items-center justify-between rounded-md border border-border bg-card px-4 py-3 text-left text-sm text-foreground transition hover:border-muted-foreground/40"
                        :aria-expanded="countersOpen"
                        @click="countersOpen = !countersOpen"
                    >
                        <span>{{ summaryLabel }}</span>
                        <ChevronDown
                            class="size-4 text-muted-foreground transition"
                            :class="countersOpen ? 'rotate-180' : ''"
                        />
                    </button>

                    <div
                        v-if="countersOpen"
                        class="space-y-3 rounded-md border border-border bg-card p-4"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-foreground">
                                    Adultos
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Edad: 13 años o más
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <button
                                    type="button"
                                    class="flex size-8 items-center justify-center rounded-full border border-border text-foreground transition hover:bg-muted disabled:opacity-40"
                                    :disabled="adults <= minAdults"
                                    aria-label="Quitar adulto"
                                    @click="decrement('adults')"
                                >
                                    <Minus class="size-4" />
                                </button>
                                <span
                                    class="w-6 text-center text-sm font-medium"
                                >
                                    {{ adults }}
                                </span>
                                <button
                                    type="button"
                                    class="flex size-8 items-center justify-center rounded-full border border-border text-foreground transition hover:bg-muted"
                                    aria-label="Agregar adulto"
                                    @click="increment('adults')"
                                >
                                    <Plus class="size-4" />
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-foreground">
                                    Niños
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Edades: 2 - 12
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <button
                                    type="button"
                                    class="flex size-8 items-center justify-center rounded-full border border-border text-foreground transition hover:bg-muted disabled:opacity-40"
                                    :disabled="children <= 0"
                                    aria-label="Quitar niño"
                                    @click="decrement('children')"
                                >
                                    <Minus class="size-4" />
                                </button>
                                <span
                                    class="w-6 text-center text-sm font-medium"
                                >
                                    {{ children }}
                                </span>
                                <button
                                    type="button"
                                    class="flex size-8 items-center justify-center rounded-full border border-border text-foreground transition hover:bg-muted"
                                    aria-label="Agregar niño"
                                    @click="increment('children')"
                                >
                                    <Plus class="size-4" />
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-foreground">
                                    Bebés
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Menos de 2 años
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <button
                                    type="button"
                                    class="flex size-8 items-center justify-center rounded-full border border-border text-foreground transition hover:bg-muted disabled:opacity-40"
                                    :disabled="babies <= 0"
                                    aria-label="Quitar bebé"
                                    @click="decrement('babies')"
                                >
                                    <Minus class="size-4" />
                                </button>
                                <span
                                    class="w-6 text-center text-sm font-medium"
                                >
                                    {{ babies }}
                                </span>
                                <button
                                    type="button"
                                    class="flex size-8 items-center justify-center rounded-full border border-border text-foreground transition hover:bg-muted"
                                    aria-label="Agregar bebé"
                                    @click="increment('babies')"
                                >
                                    <Plus class="size-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <button
                            type="button"
                            class="flex w-full items-start justify-between gap-3 rounded-md border px-4 py-3 text-left transition"
                            :class="
                                travelerMode === 'complete_now'
                                    ? 'border-primary bg-primary/5'
                                    : 'border-border hover:border-muted-foreground/40'
                            "
                            @click="travelerMode = 'complete_now'"
                        >
                            <span class="space-y-0.5">
                                <span
                                    class="block text-sm font-medium text-foreground"
                                >
                                    Completar ahora todos los datos de los
                                    viajeros
                                </span>
                                <span
                                    class="block text-xs text-muted-foreground"
                                >
                                    Tu reserva quedará lista de inmediato
                                </span>
                            </span>
                            <span
                                v-if="travelerMode === 'complete_now'"
                                class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground"
                                aria-hidden="true"
                            >
                                &check;
                            </span>
                        </button>

                        <button
                            type="button"
                            class="flex w-full items-start justify-between gap-3 rounded-md border px-4 py-3 text-left transition"
                            :class="
                                travelerMode === 'share_link'
                                    ? 'border-primary bg-primary/5'
                                    : 'border-border hover:border-muted-foreground/40'
                            "
                            @click="travelerMode = 'share_link'"
                        >
                            <span class="space-y-0.5">
                                <span
                                    class="block text-sm font-medium text-foreground"
                                >
                                    Solo completar mis datos y pagar ahora
                                </span>
                                <span
                                    class="block text-xs text-muted-foreground"
                                >
                                    Te enviaremos un enlace para que compartas
                                    con los demás viajeros para que llenen su
                                    información antes del viaje.
                                </span>
                            </span>
                            <span
                                v-if="travelerMode === 'share_link'"
                                class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground"
                                aria-hidden="true"
                            >
                                &check;
                            </span>
                        </button>
                    </div>
                </section>

                <!-- Dynamic traveler sections -->
                <template v-if="travelerMode === 'complete_now'">
                    <section
                        v-for="(traveler, index) in travelers"
                        :key="index"
                        class="space-y-4"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="flex h-7 w-7 items-center justify-center rounded-full bg-foreground text-sm font-bold text-background"
                            >
                                {{ index + 3 }}
                            </span>
                            <h2 class="text-lg font-semibold text-foreground">
                                Viajero {{ index + 1 }}
                            </h2>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label :for="`traveler-name-${index}`">
                                    Nombres y apellidos *
                                </Label>
                                <Input
                                    :id="`traveler-name-${index}`"
                                    v-model="traveler.full_name"
                                    type="text"
                                    required
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label :for="`traveler-phone-${index}`">
                                    Número celular *
                                </Label>
                                <Input
                                    :id="`traveler-phone-${index}`"
                                    v-model="traveler.phone"
                                    type="tel"
                                    placeholder="+57 300 000 0000"
                                    required
                                />
                            </div>
                        </div>
                    </section>
                </template>

                <!-- Emergency contact -->
                <section class="space-y-4">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex h-7 w-7 items-center justify-center rounded-full bg-foreground text-sm font-bold text-background"
                        >
                            {{ emergencySectionNumber }}
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
