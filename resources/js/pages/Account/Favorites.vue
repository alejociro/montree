<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type FavoriteItem = {
    id: number;
    tour: {
        id: number;
        slug: string;
        name: string;
        base_price: string;
        currency: string;
        rating_average: string;
        cover_image_url: string | null;
        is_available: boolean;
    };
};

const items = ref<FavoriteItem[]>([]);
const loading = ref(true);

onMounted(async () => {
    try {
        const response = await fetch('/api/v1/account/favorites', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const json = await response.json();
        items.value = json.data;
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
    <Head title="Mis favoritos" />
    <div class="container mx-auto max-w-5xl space-y-6 px-4 py-8">
        <h1 class="text-2xl font-bold">Mis favoritos</h1>

        <p v-if="loading" class="text-sm text-muted-foreground">Cargando...</p>

        <div
            v-else-if="items.length === 0"
            class="flex flex-col items-center gap-4 rounded-lg border border-dashed p-12 text-center"
        >
            <div class="rounded-full bg-muted p-4">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
            </div>
            <div class="space-y-1">
                <p class="font-medium">No tenés tours favoritos todavía</p>
                <p class="text-sm text-muted-foreground">Guardá tus tours favoritos para encontrarlos fácilmente.</p>
            </div>
            <Link href="/tours">
                <Button>Ver catálogo</Button>
            </Link>
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="f in items"
                :key="f.id"
                :href="`/tours/${f.tour.slug}`"
                class="group overflow-hidden rounded-lg border transition hover:shadow-md"
            >
                <div class="aspect-[4/3] overflow-hidden bg-muted">
                    <img
                        v-if="f.tour.cover_image_url"
                        :src="f.tour.cover_image_url"
                        :alt="f.tour.name"
                        class="h-full w-full object-cover transition group-hover:scale-105"
                    />
                </div>
                <div class="space-y-1 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="font-medium">{{ f.tour.name }}</h3>
                        <Badge v-if="!f.tour.is_available" variant="outline">No disponible</Badge>
                    </div>
                    <p class="text-sm font-semibold text-primary">
                        {{ formatPrice(f.tour.base_price, f.tour.currency) }}
                    </p>
                    <p class="text-xs text-muted-foreground">{{ f.tour.rating_average }} ★</p>
                </div>
            </Link>
        </div>
    </div>
</template>
