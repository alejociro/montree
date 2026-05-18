<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';

type DbNotification = {
    id: string;
    type: string;
    data: Record<string, unknown>;
    read_at: string | null;
    created_at: string;
};

const items = ref<DbNotification[]>([]);
const unreadCount = ref(0);
const loading = ref(true);

async function load() {
    loading.value = true;
    try {
        const response = await fetch('/api/v1/notifications', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const json = await response.json();
        items.value = json.data;
        unreadCount.value = json.unread_count;
    } finally {
        loading.value = false;
    }
}

async function markRead(id: string) {
    await router.patch(
        `/api/v1/notifications/${id}/read`,
        {},
        { preserveScroll: true, onSuccess: () => load() },
    );
}

async function markAllRead() {
    await router.post('/api/v1/notifications/read-all', {}, {
        preserveScroll: true,
        onSuccess: () => load(),
    });
}

onMounted(load);
</script>

<template>
    <Head title="Notificaciones" />
    <div class="container mx-auto max-w-3xl space-y-4 px-4 py-8">
        <header class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">
                Notificaciones
                <span v-if="unreadCount > 0" class="ml-2 rounded-full bg-primary px-2 py-0.5 text-xs text-primary-foreground">
                    {{ unreadCount }}
                </span>
            </h1>
            <Button v-if="unreadCount > 0" variant="outline" size="sm" @click="markAllRead">
                Marcar todo como leído
            </Button>
        </header>

        <p v-if="loading" class="text-sm text-muted-foreground">Cargando...</p>

        <div v-else-if="items.length === 0" class="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
            No tenés notificaciones todavía.
        </div>

        <ul v-else class="space-y-3">
            <li
                v-for="n in items"
                :key="n.id"
                class="flex items-start justify-between gap-3 rounded-lg border p-4"
                :class="{ 'bg-primary/5': n.read_at === null }"
            >
                <div class="flex-1">
                    <p class="font-medium">{{ (n.data as { tour_name?: string }).tour_name ?? n.type }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ new Date(n.created_at).toLocaleString('es-CO') }}
                    </p>
                </div>
                <Button
                    v-if="n.read_at === null"
                    variant="ghost"
                    size="sm"
                    @click="markRead(n.id)"
                >
                    Marcar leída
                </Button>
            </li>
        </ul>
    </div>
</template>
