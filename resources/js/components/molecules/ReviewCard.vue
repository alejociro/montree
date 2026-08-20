<script setup lang="ts">
import { computed } from 'vue';
import { intlLocale } from '@/lib/format';
import type { ReviewSummary } from '@/types/tour-detail';

const props = defineProps<{ review: ReviewSummary }>();

const formattedDate = computed(() =>
    props.review.created_at
        ? new Date(props.review.created_at).toLocaleDateString(intlLocale(), {
              year: 'numeric',
              month: 'short',
              day: 'numeric',
          })
        : '',
);

/** Iniciales del avatar: el prototipo las pinta cuando no hay foto de perfil. */
const initials = computed(() =>
    (props.review.author_name ?? '?')
        .split(' ')
        .filter((part) => part.length > 0)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join(''),
);
</script>

<template>
    <article class="rounded-2xl bg-accent p-5">
        <div class="flex items-center gap-2.5">
            <span
                class="grid size-[34px] flex-none place-items-center rounded-full bg-brand-green-100 text-[13px] font-bold text-brand-green"
                aria-hidden="true"
            >
                {{ initials }}
            </span>
            <div class="min-w-0">
                <p class="font-semibold">
                    {{ review.author_name ?? $t('Anónimo') }}
                </p>
                <p
                    class="text-[11px] tracking-[0.08em] text-muted-foreground uppercase"
                >
                    {{ formattedDate }}
                </p>
            </div>
            <span class="ml-auto tracking-[1px] text-primary">
                {{ '★'.repeat(review.rating) }}
            </span>
        </div>

        <h3 v-if="review.title" class="mt-2.5 font-medium">
            {{ review.title }}
        </h3>
        <p v-if="review.body" class="mt-2.5 text-[14.5px]">
            {{ review.body }}
        </p>

        <div
            v-if="review.admin_response"
            class="mt-3.5 rounded-xl bg-card/70 p-3 text-sm"
        >
            <p class="mb-1 font-medium">{{ $t('Respuesta de la agencia') }}</p>
            <p class="text-muted-foreground">{{ review.admin_response }}</p>
        </div>
    </article>
</template>
