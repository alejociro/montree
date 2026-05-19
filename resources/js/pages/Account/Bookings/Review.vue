<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { store as storeReview } from '@/actions/App/Http/Controllers/Api/V1/ReviewController';

type BookingForReview = {
    id: number;
    booking_number: string;
    status: string;
    tour_name: string;
    tour_slug: string;
    has_review: boolean;
};

const props = defineProps<{ booking: BookingForReview }>();

const rating = ref(0);
const hoverRating = ref(0);
const title = ref('');
const comment = ref('');
const submitting = ref(false);
const submitted = ref(false);

const canReview = props.booking.status === 'completed' && !props.booking.has_review;

function setRating(value: number) {
    rating.value = value;
}

function submit() {
    if (rating.value === 0) {
        toast.error('Seleccioná una calificación');
        return;
    }

    submitting.value = true;
    router.post(
        storeReview().url,
        {
            booking_id: props.booking.id,
            rating: rating.value,
            title: title.value || null,
            comment: comment.value || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Reseña enviada. Gracias por tu opinión.');
                submitted.value = true;
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0] as string | undefined;
                toast.error(firstError ?? 'No se pudo enviar la reseña');
            },
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="`Reseña — ${booking.tour_name}`" />
    <div class="container mx-auto max-w-2xl space-y-6 px-4 py-8">
        <div class="space-y-1">
            <Link href="/account/bookings" class="text-sm text-muted-foreground hover:underline">
                &larr; Mis reservas
            </Link>
            <h1 class="text-2xl font-bold">Escribir reseña</h1>
            <p class="text-muted-foreground">{{ booking.tour_name }}</p>
        </div>

        <div v-if="submitted" class="rounded-lg border border-primary/20 bg-primary/5 p-8 text-center space-y-3">
            <div class="text-4xl">&#10003;</div>
            <h2 class="text-lg font-semibold">Reseña enviada</h2>
            <p class="text-sm text-muted-foreground">Tu opinión será revisada por el equipo antes de publicarse.</p>
            <Link href="/account/bookings">
                <Button variant="outline" class="mt-2">Volver a mis reservas</Button>
            </Link>
        </div>

        <div v-else-if="!canReview" class="rounded-lg border border-destructive/20 bg-destructive/5 p-6 text-center space-y-2">
            <p v-if="booking.has_review" class="font-medium">Ya dejaste una reseña para esta reserva.</p>
            <p v-else class="font-medium">Solo podés reseñar reservas completadas.</p>
            <Link href="/account/bookings">
                <Button variant="outline" size="sm">Volver</Button>
            </Link>
        </div>

        <form v-else class="space-y-6 rounded-lg border p-6" @submit.prevent="submit">
            <div class="space-y-2">
                <Label>Calificación</Label>
                <div class="flex gap-1">
                    <button
                        v-for="star in 5"
                        :key="star"
                        type="button"
                        class="text-3xl transition-colors focus:outline-none"
                        :class="star <= (hoverRating || rating) ? 'text-amber-500' : 'text-muted-foreground/30'"
                        @mouseenter="hoverRating = star"
                        @mouseleave="hoverRating = 0"
                        @click="setRating(star)"
                    >
                        &#9733;
                    </button>
                </div>
            </div>

            <div class="space-y-2">
                <Label for="review-title">Título (opcional)</Label>
                <Input
                    id="review-title"
                    v-model="title"
                    maxlength="120"
                    placeholder="Resumen de tu experiencia"
                />
            </div>

            <div class="space-y-2">
                <Label for="review-comment">Comentario (opcional)</Label>
                <Textarea
                    id="review-comment"
                    v-model="comment"
                    rows="4"
                    maxlength="2000"
                    placeholder="Contanos sobre tu experiencia..."
                />
                <p class="text-xs text-muted-foreground">{{ comment.length }}/2000</p>
            </div>

            <Button type="submit" :disabled="submitting || rating === 0" class="w-full">
                {{ submitting ? 'Enviando...' : 'Enviar reseña' }}
            </Button>
        </form>
    </div>
</template>
