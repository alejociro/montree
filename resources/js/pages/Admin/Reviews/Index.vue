<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    index as indexReviews,
    updateStatus,
    respond,
} from '@/actions/App/Http/Controllers/Api/V1/Admin/ReviewController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useApi } from '@/composables/useApi';
import { useTranslations } from '@/composables/useTranslations';
import { intlLocale } from '@/lib/format';

const { t } = useTranslations();

const api = useApi();

type AdminReview = {
    id: number;
    rating: number;
    title: string | null;
    comment: string | null;
    status: string;
    rejection_reason: string | null;
    admin_response: string | null;
    admin_responded_at: string | null;
    approved_at: string | null;
    created_at: string | null;
    tour: { id: number; name: string; slug: string } | null;
    user: { id: number; name: string } | null;
};

const items = ref<AdminReview[]>([]);
const loading = ref(true);
const activeTab = ref<'pending' | 'approved' | 'rejected'>('pending');

const filteredItems = computed(() =>
    items.value.filter((r) => r.status === activeTab.value),
);

const tabs: { key: 'pending' | 'approved' | 'rejected'; label: string }[] = [
    { key: 'pending', label: t('Pendientes') },
    { key: 'approved', label: t('Aprobadas') },
    { key: 'rejected', label: t('Rechazadas') },
];

const pendingCount = computed(
    () => items.value.filter((r) => r.status === 'pending').length,
);

async function load() {
    loading.value = true;

    try {
        const res = await fetch(indexReviews().url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const json = await res.json();
        items.value = json.data ?? [];
    } finally {
        loading.value = false;
    }
}

function approve(reviewId: number) {
    void api.patch(
        updateStatus.url(reviewId),
        { status: 'approved' },
        {
            onSuccess: () => {
                toast.success(t('Reseña aprobada'));
                void load();
            },
            onError: (e) => toast.error(Object.values(e)[0] ?? 'Error'),
        },
    );
}

function reject(reviewId: number) {
    void api.patch(
        updateStatus.url(reviewId),
        { status: 'rejected' },
        {
            onSuccess: () => {
                toast.success(t('Reseña rechazada'));
                void load();
            },
            onError: (e) => toast.error(Object.values(e)[0] ?? 'Error'),
        },
    );
}

const respondDialog = ref(false);
const respondReviewId = ref<number | null>(null);
const respondText = ref('');
const respondSubmitting = ref(false);

function openRespondDialog(reviewId: number) {
    respondReviewId.value = reviewId;
    respondText.value = '';
    respondDialog.value = true;
}

function submitResponse() {
    if (!respondReviewId.value || !respondText.value.trim()) {
        return;
    }

    respondSubmitting.value = true;
    void api.post(
        respond.url(respondReviewId.value),
        { response: respondText.value },
        {
            onSuccess: () => {
                toast.success(t('Respuesta enviada'));
                respondDialog.value = false;
                void load();
            },
            onError: (e) => toast.error(Object.values(e)[0] ?? 'Error'),
            onFinish: () => {
                respondSubmitting.value = false;
            },
        },
    );
}

function formatDate(date: string | null): string {
    if (!date) {
        return '';
    }

    return new Date(date).toLocaleDateString(intlLocale(), {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

onMounted(load);
</script>

<template>
    <Head :title="$t('Reseñas')" />
    <div class="container mx-auto max-w-4xl space-y-6 px-4 py-8">
        <header class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">
                {{ $t('Reseñas') }}
                <span
                    v-if="pendingCount > 0"
                    class="ml-2 rounded-full bg-primary px-2.5 py-0.5 text-xs font-medium text-primary-foreground"
                >
                    {{ pendingCount }}
                </span>
            </h1>
        </header>

        <nav class="flex gap-1 rounded-lg border p-1">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                class="flex-1 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                :class="
                    activeTab === tab.key
                        ? 'bg-primary text-primary-foreground'
                        : 'text-muted-foreground hover:bg-muted'
                "
                @click="activeTab = tab.key"
            >
                {{ tab.label }}
            </button>
        </nav>

        <p v-if="loading" class="text-sm text-muted-foreground">
            {{ $t('Cargando...') }}
        </p>

        <div
            v-else-if="filteredItems.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-muted-foreground"
        >
            {{
                activeTab === 'pending'
                    ? $t('No hay reseñas pendientes.')
                    : activeTab === 'approved'
                      ? $t('No hay reseñas aprobadas.')
                      : $t('No hay reseñas rechazadas.')
            }}
        </div>

        <ul v-else class="space-y-3">
            <li
                v-for="r in filteredItems"
                :key="r.id"
                class="space-y-3 rounded-lg border p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="font-medium">{{
                                r.user?.name ?? $t('Anónimo')
                            }}</span>
                            <span class="text-primary"
                                >{{ '★'.repeat(r.rating)
                                }}{{ '☆'.repeat(5 - r.rating) }}</span
                            >
                            <Badge
                                :variant="
                                    r.status === 'approved'
                                        ? 'default'
                                        : r.status === 'rejected'
                                          ? 'destructive'
                                          : 'secondary'
                                "
                            >
                                {{
                                    r.status === 'pending'
                                        ? $t('Pendiente')
                                        : r.status === 'approved'
                                          ? $t('Aprobada')
                                          : $t('Rechazada')
                                }}
                            </Badge>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ r.tour?.name ?? $t('Tour eliminado') }} ·
                            {{ formatDate(r.created_at) }}
                        </p>
                    </div>
                </div>

                <h3 v-if="r.title" class="font-medium">{{ r.title }}</h3>
                <p v-if="r.comment" class="text-sm text-muted-foreground">
                    {{ r.comment }}
                </p>

                <div
                    v-if="r.admin_response"
                    class="rounded-md bg-muted/50 p-3 text-sm"
                >
                    <p class="mb-1 font-medium">{{ $t('Tu respuesta') }}</p>
                    <p class="text-muted-foreground">{{ r.admin_response }}</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="r.status === 'pending'"
                        size="sm"
                        @click="approve(r.id)"
                    >
                        {{ $t('Aprobar') }}
                    </Button>
                    <Button
                        v-if="r.status === 'pending'"
                        variant="outline"
                        size="sm"
                        @click="reject(r.id)"
                    >
                        {{ $t('Rechazar') }}
                    </Button>
                    <Button
                        v-if="r.status === 'approved' && !r.admin_response"
                        variant="outline"
                        size="sm"
                        @click="openRespondDialog(r.id)"
                    >
                        {{ $t('Responder') }}
                    </Button>
                </div>
            </li>
        </ul>

        <Dialog v-model:open="respondDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ $t('Responder reseña') }}</DialogTitle>
                    <DialogDescription>
                        {{
                            $t(
                                'Tu respuesta será visible públicamente junto a la reseña del cliente.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-2">
                    <Label for="admin-response">{{ $t('Respuesta') }}</Label>
                    <Textarea
                        id="admin-response"
                        v-model="respondText"
                        rows="4"
                        maxlength="1000"
                        :placeholder="$t('Gracias por tu reseña...')"
                    />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="respondDialog = false">{{
                        $t('Cancelar')
                    }}</Button>
                    <Button
                        :disabled="respondSubmitting || !respondText.trim()"
                        @click="submitResponse"
                    >
                        {{
                            respondSubmitting
                                ? $t('Enviando...')
                                : $t('Enviar respuesta')
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
