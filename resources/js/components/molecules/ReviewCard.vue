<script setup lang="ts">
import { computed } from 'vue';
import type { ReviewSummary } from '@/types/tour-detail';

const props = defineProps<{ review: ReviewSummary }>();

const formattedDate = computed(() =>
    props.review.created_at
        ? new Date(props.review.created_at).toLocaleDateString('es-CO', {
              year: 'numeric',
              month: 'short',
              day: 'numeric',
          })
        : '',
);
</script>

<template>
    <article class="space-y-2 rounded-lg border p-4">
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <span class="font-medium">{{
                    review.author_name ?? 'Anónimo'
                }}</span>
                <span class="text-sm text-muted-foreground"
                    >· {{ formattedDate }}</span
                >
            </div>
            <span class="text-amber-500">{{ '★'.repeat(review.rating) }}</span>
        </div>
        <h3 v-if="review.title" class="font-medium">{{ review.title }}</h3>
        <p v-if="review.body" class="text-sm text-muted-foreground">
            {{ review.body }}
        </p>
        <div
            v-if="review.admin_response"
            class="mt-3 rounded-md bg-muted/50 p-3 text-sm"
        >
            <p class="mb-1 font-medium">Respuesta de la agencia</p>
            <p class="text-muted-foreground">{{ review.admin_response }}</p>
        </div>
    </article>
</template>
