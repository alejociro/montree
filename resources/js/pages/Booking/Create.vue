<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useApi } from '@/composables/useApi';

type BookingCreateResponse = {
    data?: { booking_number?: string };
};

const api = useApi();

type Tour = {
    id: number;
    slug: string;
    name: string;
    description: string;
    cover_image_url: string | null;
};

type TourDate = {
    id: number;
    starts_at: string;
    ends_at: string | null;
    effective_price: string;
    available_seats: number;
    currency: string;
};

const props = defineProps<{
    tour: Tour;
    tourDate: TourDate;
    requireTravelers: boolean;
}>();

type Traveler = {
    full_name: string;
    email: string | null;
    phone: string | null;
};

const travelers = ref<Traveler[]>([
    { full_name: '', email: null, phone: null },
]);

const form = useForm({
    tour_date_id: props.tourDate.id,
    travelers_count: 1,
    promotion_code: '',
    special_requests: '',
    travelers: [] as Traveler[],
});

const submitting = ref(false);

function syncTravelers() {
    const count = form.travelers_count;

    if (count > travelers.value.length) {
        for (let i = travelers.value.length; i < count; i++) {
            travelers.value.push({ full_name: '', email: null, phone: null });
        }
    } else {
        travelers.value = travelers.value.slice(0, count);
    }
}

const formattedPrice = computed(() =>
    new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: props.tourDate.currency,
        maximumFractionDigits: 0,
    }).format(Number(props.tourDate.effective_price)),
);

const estimatedSubtotal = computed(() =>
    new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: props.tourDate.currency,
        maximumFractionDigits: 0,
    }).format(Number(props.tourDate.effective_price) * form.travelers_count),
);

function submit() {
    form.travelers = props.requireTravelers ? travelers.value : [];
    form.clearErrors();
    submitting.value = true;

    void api.post<BookingCreateResponse>('/api/v1/bookings', form.data(), {
        onSuccess: (response) => {
            const bookingNumber = response?.data?.booking_number ?? null;

            if (bookingNumber) {
                router.visit(`/bookings/${bookingNumber}`);
            } else {
                toast.success('Reserva creada');
            }
        },
        onError: (errors) => {
            form.setError(errors);
            const firstError = Object.values(errors)[0];
            toast.error(firstError ?? 'No pudimos crear la reserva.');
        },
        onFinish: () => {
            submitting.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`Reservar ${tour.name}`" />
    <div class="container mx-auto max-w-3xl space-y-6 px-4 py-8">
        <header class="space-y-2">
            <p class="text-sm text-muted-foreground">Reservar tour</p>
            <h1 class="text-2xl font-bold">{{ tour.name }}</h1>
            <p class="text-sm text-muted-foreground">
                {{ new Date(tourDate.starts_at).toLocaleString('es-CO') }} ·
                {{ formattedPrice }} por persona ·
                {{ tourDate.available_seats }} cupos disponibles
            </p>
        </header>

        <form class="space-y-6" @submit.prevent="submit">
            <div class="space-y-2">
                <Label for="travelers_count">Cantidad de viajeros</Label>
                <Input
                    id="travelers_count"
                    v-model.number="form.travelers_count"
                    type="number"
                    min="1"
                    :max="tourDate.available_seats"
                    @change="syncTravelers"
                />
                <p
                    v-if="form.errors.travelers_count"
                    class="text-sm text-destructive"
                >
                    {{ form.errors.travelers_count }}
                </p>
            </div>

            <div v-if="requireTravelers" class="space-y-4">
                <h2 class="text-lg font-semibold">Datos de los viajeros</h2>
                <div
                    v-for="(t, i) in travelers"
                    :key="i"
                    class="space-y-3 rounded-lg border p-4"
                >
                    <p class="text-sm font-medium">Viajero {{ i + 1 }}</p>
                    <div class="space-y-2">
                        <Label :for="`name-${i}`">Nombre completo</Label>
                        <Input
                            :id="`name-${i}`"
                            v-model="t.full_name"
                            placeholder="Tu nombre completo"
                            required
                        />
                    </div>
                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label :for="`email-${i}`">Email</Label>
                            <Input
                                :id="`email-${i}`"
                                v-model="t.email"
                                type="email"
                                placeholder="tu@correo.com"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label :for="`phone-${i}`">Teléfono</Label>
                            <Input
                                :id="`phone-${i}`"
                                v-model="t.phone"
                                placeholder="+57 300..."
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-2">
                <Label for="promotion_code"
                    >Código promocional (opcional)</Label
                >
                <Input
                    id="promotion_code"
                    v-model="form.promotion_code"
                    placeholder="VERANO2026"
                />
            </div>

            <div class="space-y-2">
                <Label for="special_requests"
                    >Notas adicionales (opcional)</Label
                >
                <Textarea
                    id="special_requests"
                    v-model="form.special_requests"
                    rows="3"
                    placeholder="Alergias, transporte, requerimientos..."
                />
            </div>

            <Alert>
                <AlertDescription class="flex items-center justify-between">
                    <span>Subtotal estimado</span>
                    <strong>{{ estimatedSubtotal }}</strong>
                </AlertDescription>
            </Alert>

            <Button type="submit" :disabled="submitting" class="w-full">
                {{ submitting ? 'Procesando...' : 'Crear reserva' }}
            </Button>
        </form>
    </div>
</template>
