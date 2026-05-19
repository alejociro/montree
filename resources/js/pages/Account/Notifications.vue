<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useApi } from '@/composables/useApi';

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
const api = useApi();

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

function markRead(id: string) {
    void api.patch(
        `/api/v1/notifications/${id}/read`,
        {},
        {
            onSuccess: () => {
                void load();
            },
        },
    );
}

function markAllRead() {
    void api.post(
        '/api/v1/notifications/read-all',
        {},
        {
            onSuccess: () => {
                void load();
            },
        },
    );
}

onMounted(load);
</script>

<template>
    <Head title="Notificaciones" />
    <div class="container mx-auto max-w-3xl space-y-4 px-4 py-8">
        <header class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">
                Notificaciones
                <span
                    v-if="unreadCount > 0"
                    class="ml-2 rounded-full bg-primary px-2 py-0.5 text-xs text-primary-foreground"
                >
                    {{ unreadCount }}
                </span>
            </h1>
            <Button
                v-if="unreadCount > 0"
                variant="outline"
                size="sm"
                @click="markAllRead"
            >
                Marcar todo como leído
            </Button>
        </header>

        <p v-if="loading" class="text-sm text-muted-foreground">Cargando...</p>

        <div
            v-else-if="items.length === 0"
            class="flex flex-col items-center gap-4 rounded-lg border border-dashed p-12 text-center"
        >
            <div class="rounded-full bg-muted p-4">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="32"
                    height="32"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="text-muted-foreground"
                >
                    <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
                    <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
                </svg>
            </div>
            <div class="space-y-1">
                <p class="font-medium">No tenés notificaciones todavía</p>
                <p class="text-sm text-muted-foreground">
                    Acá vas a ver las novedades sobre tus reservas y tours.
                </p>
            </div>
        </div>

        <ul v-else class="space-y-3">
            <li
                v-for="n in items"
                :key="n.id"
                class="flex items-start justify-between gap-3 rounded-lg border p-4"
                :class="{ 'bg-primary/5': n.read_at === null }"
            >
                <div class="flex-1">
                    <p class="font-medium">
                        {{
                            (n.data as { tour_name?: string }).tour_name ??
                            n.type
                        }}
                    </p>
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
