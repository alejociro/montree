<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';

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

const formattedTotal = computed(() =>
    new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: props.booking.currency,
        maximumFractionDigits: 0,
    }).format(Number(props.booking.total_amount)),
);

const expiresAt = computed(() =>
    props.booking.expires_at
        ? new Date(props.booking.expires_at).toLocaleString('es-CO')
        : null,
);

const statusVariant = computed(() => {
    if (props.booking.status === 'confirmed') return 'default';
    if (props.booking.status === 'pending_payment') return 'secondary';
    return 'outline';
});
</script>

<template>
    <Head :title="`Reserva ${booking.booking_number}`" />
    <div class="container mx-auto max-w-2xl space-y-6 px-4 py-8">
        <header class="space-y-2">
            <p class="text-sm text-muted-foreground">Reserva</p>
            <h1 class="text-2xl font-bold">{{ booking.booking_number }}</h1>
            <Badge :variant="statusVariant">{{ booking.status }}</Badge>
        </header>

        <Alert v-if="booking.status === 'pending_payment'">
            <AlertTitle>Pago pendiente</AlertTitle>
            <AlertDescription>
                Tenés tiempo hasta <strong>{{ expiresAt }}</strong> para completar el pago.
                (Integración Stripe pendiente — F007.)
            </AlertDescription>
        </Alert>

        <dl class="grid gap-4 rounded-lg border p-6 text-sm">
            <div class="flex justify-between">
                <dt class="text-muted-foreground">Tour</dt>
                <dd class="font-medium">{{ booking.tour_name }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-muted-foreground">Fecha</dt>
                <dd>{{ new Date(booking.starts_at).toLocaleString('es-CO') }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-muted-foreground">Viajeros</dt>
                <dd>{{ booking.travelers_count }}</dd>
            </div>
            <div class="flex justify-between border-t pt-3">
                <dt>Total</dt>
                <dd class="text-lg font-bold text-primary">{{ formattedTotal }}</dd>
            </div>
        </dl>
    </div>
</template>
