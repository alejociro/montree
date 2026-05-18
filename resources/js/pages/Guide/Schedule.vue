<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

type ScheduleItem = {
    id: number;
    starts_at: string;
    ends_at: string | null;
    capacity_total: number;
    capacity_booked: number;
    tour: { id: number; name: string; slug: string };
};

const items = ref<ScheduleItem[]>([]);
const loading = ref(true);

onMounted(async () => {
    const res = await fetch('/api/v1/guide/schedule', {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });
    const json = await res.json();
    items.value = json.data;
    loading.value = false;
});
</script>

<template>
    <Head title="Mi agenda" />
    <div class="container mx-auto max-w-3xl space-y-4 px-4 py-8">
        <h1 class="text-2xl font-bold">Mi agenda</h1>
        <p v-if="loading" class="text-sm text-muted-foreground">Cargando...</p>
        <div v-else-if="items.length === 0" class="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
            No tenés tours asignados próximamente.
        </div>
        <ul v-else class="space-y-3">
            <li v-for="d in items" :key="d.id" class="rounded-lg border p-4">
                <p class="font-medium">{{ d.tour.name }}</p>
                <p class="text-sm text-muted-foreground">
                    {{ new Date(d.starts_at).toLocaleString('es-CO') }} ·
                    {{ d.capacity_booked }}/{{ d.capacity_total }} viajeros
                </p>
            </li>
        </ul>
    </div>
</template>
