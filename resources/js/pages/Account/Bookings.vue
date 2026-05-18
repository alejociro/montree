<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { Badge } from '@/components/ui/badge';

type BookingSummary = {
    booking_number: string;
    status: string;
    total_amount: string;
    currency: string;
    travelers_count: number;
    tour: { id: number; slug: string; name: string };
    starts_at: string;
    expires_at: string | null;
};

const upcoming = ref<BookingSummary[]>([]);
const past = ref<BookingSummary[]>([]);
const cancelled = ref<BookingSummary[]>([]);
const loading = ref(true);

onMounted(async () => {
    try {
        const response = await fetch('/api/v1/account/bookings', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const json = await response.json();
        upcoming.value = json.data.upcoming;
        past.value = json.data.past;
        cancelled.value = json.data.cancelled;
    } finally {
        loading.value = false;
    }
});

function formatPrice(amount: string, currency: string) {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency,
        maximumFractionDigits: 0,
    }).format(Number(amount));
}
</script>

<template>
    <Head title="Mis reservas" />
    <div class="container mx-auto max-w-4xl space-y-8 px-4 py-8">
        <h1 class="text-2xl font-bold">Mis reservas</h1>

        <p v-if="loading" class="text-sm text-muted-foreground">Cargando...</p>

        <section v-if="!loading && upcoming.length > 0" class="space-y-3">
            <h2 class="text-lg font-semibold">Próximas</h2>
            <ul class="space-y-3">
                <li v-for="b in upcoming" :key="b.booking_number" class="rounded-lg border p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <Link
                                :href="`/bookings/${b.booking_number}`"
                                class="font-medium hover:underline"
                            >
                                {{ b.tour.name }}
                            </Link>
                            <p class="text-sm text-muted-foreground">
                                {{ new Date(b.starts_at).toLocaleString('es-CO') }} ·
                                {{ b.travelers_count }} viajero(s) ·
                                {{ formatPrice(b.total_amount, b.currency) }}
                            </p>
                        </div>
                        <Badge :variant="b.status === 'confirmed' ? 'default' : 'secondary'">
                            {{ b.status }}
                        </Badge>
                    </div>
                </li>
            </ul>
        </section>

        <section v-if="!loading && past.length > 0" class="space-y-3">
            <h2 class="text-lg font-semibold">Anteriores</h2>
            <ul class="space-y-3">
                <li v-for="b in past" :key="b.booking_number" class="rounded-lg border p-4">
                    <Link
                        :href="`/bookings/${b.booking_number}`"
                        class="font-medium hover:underline"
                    >
                        {{ b.tour.name }}
                    </Link>
                    <p class="text-sm text-muted-foreground">
                        {{ new Date(b.starts_at).toLocaleString('es-CO') }}
                    </p>
                </li>
            </ul>
        </section>

        <section v-if="!loading && cancelled.length > 0" class="space-y-3">
            <h2 class="text-lg font-semibold">Canceladas</h2>
            <ul class="space-y-2">
                <li v-for="b in cancelled" :key="b.booking_number" class="text-sm text-muted-foreground">
                    {{ b.tour.name }} ({{ b.status }})
                </li>
            </ul>
        </section>

        <p
            v-if="!loading && upcoming.length === 0 && past.length === 0 && cancelled.length === 0"
            class="text-muted-foreground"
        >
            No tenés reservas todavía.
            <Link href="/tours" class="text-primary hover:underline">Ver tours</Link>
        </p>
    </div>
</template>
