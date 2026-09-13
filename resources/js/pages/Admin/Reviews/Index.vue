<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Check,
    MessageSquare,
    RotateCcw,
    Star,
    StarOff,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    index as indexReviews,
    respond,
    updateStatus,
} from '@/actions/App/Http/Controllers/Api/V1/Admin/ReviewController';
import InitialsAvatar from '@/components/atoms/InitialsAvatar.vue';
import KpiCard from '@/components/atoms/KpiCard.vue';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import Heading from '@/components/Heading.vue';
import CountTabs from '@/components/molecules/CountTabs.vue';
import type { CountTab } from '@/components/molecules/CountTabs.vue';
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
import { formatNumber, intlLocale } from '@/lib/format';

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

function countOf(status: string): number {
    return items.value.filter((review) => review.status === status).length;
}

const tabs = computed<CountTab[]>(() => [
    { id: 'pending', label: t('Pendientes'), count: countOf('pending') },
    { id: 'approved', label: t('Aprobadas'), count: countOf('approved') },
    { id: 'rejected', label: t('Rechazadas'), count: countOf('rejected') },
]);

/**
 * El promedio se calcula SOLO sobre lo publicado: incluir lo pendiente o lo
 * rechazado daría una nota que no existe en ninguna ficha pública.
 */
const stats = computed(() => {
    const approved = items.value.filter(
        (review) => review.status === 'approved',
    );
    const sum = approved.reduce((total, review) => total + review.rating, 0);

    return {
        pending: countOf('pending'),
        approved: approved.length,
        rejected: countOf('rejected'),
        average:
            approved.length === 0
                ? null
                : Math.round((sum / approved.length) * 10) / 10,
    };
});

const emptyMessages: Record<string, { title: string; hint: string }> = {
    pending: {
        title: t('Nada por moderar'),
        hint: t(
            'Cuando alguien reseñe un tour aparecerá acá para aprobarla o rechazarla.',
        ),
    },
    approved: {
        title: t('Todavía no hay reseñas publicadas'),
        hint: t('Las que apruebes se muestran en la ficha pública del tour.'),
    },
    rejected: {
        title: t('Ninguna reseña rechazada'),
        hint: t(
            'Las rechazadas quedan acá por si hay que devolverlas a revisión.',
        ),
    },
};

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

function reopen(reviewId: number) {
    void api.patch(
        updateStatus.url(reviewId),
        { status: 'pending' },
        {
            onSuccess: () => {
                toast.success(t('Reseña devuelta a revisión'));
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

    <div class="px-4 py-6 md:px-8">
        <Heading
            :title="$t('Reseñas')"
            :description="$t('Qué dicen los viajeros y qué falta por moderar.')"
        />

        <div class="mt-5 grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
            <KpiCard
                :label="$t('Por moderar')"
                :value="formatNumber(stats.pending)"
                :detail="$t('esperan tu decisión')"
                :alert="stats.pending > 0"
                :loading="loading"
            />
            <KpiCard
                :label="$t('Publicadas')"
                :value="formatNumber(stats.approved)"
                :detail="$t('visibles en el catálogo')"
                :loading="loading"
            />
            <KpiCard
                :label="$t('Promedio publicado')"
                :value="stats.average === null ? '—' : String(stats.average)"
                :detail="$t('sobre 5 estrellas')"
                :loading="loading"
            />
            <KpiCard
                :label="$t('Rechazadas')"
                :value="formatNumber(stats.rejected)"
                :detail="$t('no se muestran')"
                :loading="loading"
            />
        </div>

        <div class="mt-5 rounded-2xl border border-border bg-card p-3.5">
            <CountTabs
                :tabs="tabs"
                :model-value="activeTab"
                :label="$t('Bandejas de reseñas')"
                @update:model-value="
                    (value) =>
                        (activeTab = value as
                            | 'pending'
                            | 'approved'
                            | 'rejected')
                "
            />
        </div>

        <div v-if="loading" class="mt-4 space-y-3">
            <div
                v-for="n in 3"
                :key="n"
                class="h-28 animate-pulse rounded-2xl bg-muted"
            />
        </div>

        <div
            v-else-if="filteredItems.length === 0"
            class="mt-4 flex flex-col items-center gap-3 rounded-2xl border border-dashed border-input p-12 text-center"
        >
            <StarOff class="size-8 text-muted-foreground/40" />
            <div class="space-y-1">
                <p class="text-base font-medium">
                    {{ emptyMessages[activeTab].title }}
                </p>
                <p class="text-sm text-muted-foreground">
                    {{ emptyMessages[activeTab].hint }}
                </p>
            </div>
        </div>

        <ul v-else class="mt-4 space-y-3">
            <li
                v-for="r in filteredItems"
                :key="r.id"
                class="rounded-2xl border border-border bg-card p-4 md:p-5"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex min-w-0 gap-3">
                        <InitialsAvatar :name="r.user?.name ?? null" />
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span
                                    class="flex items-center gap-0.5"
                                    :aria-label="
                                        $t(':count de 5 estrellas', {
                                            count: r.rating,
                                        })
                                    "
                                >
                                    <Star
                                        v-for="index in 5"
                                        :key="index"
                                        class="size-3.5"
                                        :class="
                                            index <= r.rating
                                                ? 'fill-brand-warn text-brand-warn'
                                                : 'text-border'
                                        "
                                    />
                                </span>
                                <span class="text-sm font-semibold">
                                    {{ r.user?.name ?? $t('Anónimo') }}
                                </span>
                            </div>
                            <MonoLabel class="mt-1.5">
                                {{ r.tour?.name ?? $t('Tour eliminado') }} ·
                                {{ formatDate(r.created_at) }}
                            </MonoLabel>
                        </div>
                    </div>

                    <!--
                      Acciones explícitas y no un menú ⋯: moderar es la tarea
                      de esta pantalla, y esconderla tras dos clics la duplica.
                    -->
                    <div class="flex flex-wrap items-center gap-2">
                        <template v-if="r.status === 'pending'">
                            <Button size="sm" @click="approve(r.id)">
                                <Check class="size-4" />
                                {{ $t('Aprobar') }}
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                class="text-destructive"
                                @click="reject(r.id)"
                            >
                                <X class="size-4" />
                                {{ $t('Rechazar') }}
                            </Button>
                        </template>
                        <template v-else>
                            <Button
                                v-if="
                                    r.status === 'approved' && !r.admin_response
                                "
                                size="sm"
                                variant="outline"
                                @click="openRespondDialog(r.id)"
                            >
                                <MessageSquare class="size-4" />
                                {{ $t('Responder') }}
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                @click="reopen(r.id)"
                            >
                                <RotateCcw class="size-4" />
                                {{ $t('Volver a revisión') }}
                            </Button>
                        </template>
                    </div>
                </div>

                <h3 v-if="r.title" class="mt-3.5 text-sm font-semibold">
                    {{ r.title }}
                </h3>
                <p
                    v-if="r.comment"
                    class="mt-1.5 max-w-[76ch] text-sm text-muted-foreground"
                >
                    {{ r.comment }}
                </p>

                <div
                    v-if="r.admin_response"
                    class="mt-3.5 rounded-xl border border-border bg-primary-soft p-3"
                >
                    <MonoLabel>{{ $t('Tu respuesta') }}</MonoLabel>
                    <p class="mt-1.5 max-w-[76ch] text-sm">
                        {{ r.admin_response }}
                    </p>
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
