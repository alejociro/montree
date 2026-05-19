<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type BookingSummary = {
    booking_number: string;
    status: string;
    total_amount: string;
    currency: string;
    travelers_count: number;
    tour: { id: number; slug: string; name: string };
    starts_at: string;
    expires_at: string | null;
    has_review?: boolean;
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
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <Link
                                :href="`/bookings/${b.booking_number}`"
                                class="font-medium hover:underline"
                            >
                                {{ b.tour.name }}
                            </Link>
                            <p class="text-sm text-muted-foreground">
                                {{ new Date(b.starts_at).toLocaleString('es-CO') }}
                            </p>
                        </div>
                        <Link
                            v-if="b.status === 'completed' && !b.has_review"
                            :href="`/account/bookings/${b.booking_number}/review`"
                        >
                            <Button variant="outline" size="sm">Dejar reseña</Button>
                        </Link>
                        <Badge v-else-if="b.has_review" variant="secondary">Reseñado</Badge>
                    </div>
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

        <div
            v-if="!loading && upcoming.length === 0 && past.length === 0 && cancelled.length === 0"
            class="flex flex-col items-center gap-4 rounded-lg border border-dashed p-12 text-center"
        >
            <div class="rounded-full bg-muted p-4">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/></svg>
            </div>
            <div class="space-y-1">
                <p class="font-medium">No tenés reservas todavía</p>
                <p class="text-sm text-muted-foreground">Explorá los tours disponibles y reservá tu próxima aventura.</p>
            </div>
            <Link href="/tours">
                <Button>Ver tours</Button>
            </Link>
        </div>
    </div>
</template>
