<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import {
    schedule as scheduleUrl,
    travelers as travelersUrl,
} from '@/actions/App/Http/Controllers/Api/V1/GuideController';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type ScheduleItem = {
    id: number;
    starts_at: string;
    ends_at: string | null;
    capacity_total: number;
    capacity_booked: number;
    tour: { id: number; name: string; slug: string };
};

type Traveler = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    count: number;
};

const items = ref<ScheduleItem[]>([]);
const loading = ref(true);

const travelerDialog = ref(false);
const selectedDate = ref<ScheduleItem | null>(null);
const travelers = ref<Traveler[]>([]);
const loadingTravelers = ref(false);

onMounted(async () => {
    try {
        const res = await fetch(scheduleUrl().url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const json = await res.json();
        items.value = json.data;
    } finally {
        loading.value = false;
    }
});

async function openTravelers(item: ScheduleItem) {
    selectedDate.value = item;
    travelerDialog.value = true;
    loadingTravelers.value = true;
    travelers.value = [];

    try {
        const res = await fetch(travelersUrl.url(item.id), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const json = await res.json();
        travelers.value = json.data ?? [];
    } finally {
        loadingTravelers.value = false;
    }
}

function formatDate(date: string): string {
    return new Date(date).toLocaleDateString('es-CO', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

function formatTime(date: string): string {
    return new Date(date).toLocaleTimeString('es-CO', {
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head title="Mi agenda" />
    <div class="container mx-auto max-w-3xl space-y-4 px-4 py-8">
        <h1 class="text-2xl font-bold">Mi agenda</h1>
        <p v-if="loading" class="text-sm text-muted-foreground">Cargando...</p>
        <div
            v-else-if="items.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-muted-foreground"
        >
            No tenés tours asignados próximamente.
        </div>
        <ul v-else class="space-y-3">
            <li
                v-for="d in items"
                :key="d.id"
                class="cursor-pointer rounded-lg border p-4 transition-colors hover:bg-muted/50"
                @click="openTravelers(d)"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-medium">{{ d.tour.name }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ formatDate(d.starts_at) }} ·
                            {{ formatTime(d.starts_at) }}
                        </p>
                    </div>
                    <Badge variant="secondary">
                        {{ d.capacity_booked }}/{{ d.capacity_total }} viajeros
                    </Badge>
                </div>
            </li>
        </ul>

        <Dialog v-model:open="travelerDialog">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        Viajeros — {{ selectedDate?.tour.name }}
                    </DialogTitle>
                    <p
                        v-if="selectedDate"
                        class="text-sm text-muted-foreground"
                    >
                        {{ formatDate(selectedDate.starts_at) }} ·
                        {{ formatTime(selectedDate.starts_at) }}
                    </p>
                </DialogHeader>

                <p
                    v-if="loadingTravelers"
                    class="text-sm text-muted-foreground"
                >
                    Cargando viajeros...
                </p>

                <div
                    v-else-if="travelers.length === 0"
                    class="rounded-md border border-dashed p-4 text-center text-sm text-muted-foreground"
                >
                    No hay viajeros registrados para esta fecha.
                </div>

                <ul v-else class="space-y-3">
                    <li
                        v-for="t in travelers"
                        :key="t.id"
                        class="flex items-start justify-between gap-3 rounded-md border p-3"
                    >
                        <div class="space-y-0.5">
                            <p class="font-medium">{{ t.name }}</p>
                            <p
                                v-if="t.email"
                                class="text-sm text-muted-foreground"
                            >
                                {{ t.email }}
                            </p>
                            <p
                                v-if="t.phone"
                                class="text-sm text-muted-foreground"
                            >
                                {{ t.phone }}
                            </p>
                        </div>
                        <Badge v-if="t.count > 1" variant="secondary">
                            {{ t.count }} personas
                        </Badge>
                    </li>
                </ul>
            </DialogContent>
        </Dialog>
    </div>
</template>
