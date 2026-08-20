<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatCurrency } from '@/lib/format';
import type { CatalogCategory } from '@/types/catalog';
import type { TourDifficulty } from '@/types/tour';

const { t } = useTranslations();

type Props = {
    search: string | null;
    category: string | null;
    difficulty: TourDifficulty | null;
    priceMin: number | null;
    priceMax: number | null;
    categories: CatalogCategory[];
    currency: string;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (
        event: 'clear',
        filter: 'search' | 'category' | 'difficulty' | 'price',
    ): void;
}>();

const difficultyLabel: Record<TourDifficulty, string> = {
    easy: t('Fácil'),
    moderate: t('Moderado'),
    hard: t('Difícil'),
    extreme: t('Extremo'),
};

const categoryLabel = computed(() => {
    if (!props.category) {
        return null;
    }

    return (
        props.categories.find((category) => category.slug === props.category)
            ?.name ?? props.category
    );
});

const priceLabel = computed(() => {
    if (props.priceMin === null && props.priceMax === null) {
        return null;
    }

    if (props.priceMin !== null && props.priceMax !== null) {
        return `${formatCurrency(props.priceMin, props.currency)} - ${formatCurrency(props.priceMax, props.currency)}`;
    }

    if (props.priceMin !== null) {
        return t('Desde :amount', {
            amount: formatCurrency(props.priceMin, props.currency),
        });
    }

    return t('Hasta :amount', {
        amount: formatCurrency(props.priceMax ?? 0, props.currency),
    });
});

const hasAny = computed(
    () =>
        !!props.search ||
        !!props.category ||
        !!props.difficulty ||
        props.priceMin !== null ||
        props.priceMax !== null,
);

function emitClear(
    filter: 'search' | 'category' | 'difficulty' | 'price',
): void {
    emit('clear', filter);
}
</script>

<template>
    <div v-if="hasAny" class="flex flex-wrap items-center gap-2">
        <span class="text-xs text-muted-foreground">{{
            $t('Filtros activos:')
        }}</span>
        <button
            v-if="search"
            type="button"
            class="inline-flex items-center gap-1 rounded-full border border-border bg-accent px-2.5 py-1 text-xs text-accent-foreground transition hover:bg-accent/80"
            @click="emitClear('search')"
        >
            <span>{{ $t('Búsqueda: ":term"', { term: search }) }}</span>
            <X class="size-3" />
        </button>
        <button
            v-if="categoryLabel"
            type="button"
            class="inline-flex items-center gap-1 rounded-full border border-border bg-accent px-2.5 py-1 text-xs text-accent-foreground transition hover:bg-accent/80"
            @click="emitClear('category')"
        >
            <span>{{ categoryLabel }}</span>
            <X class="size-3" />
        </button>
        <button
            v-if="difficulty"
            type="button"
            class="inline-flex items-center gap-1 rounded-full border border-border bg-accent px-2.5 py-1 text-xs text-accent-foreground transition hover:bg-accent/80"
            @click="emitClear('difficulty')"
        >
            <span>{{ difficultyLabel[difficulty] }}</span>
            <X class="size-3" />
        </button>
        <button
            v-if="priceLabel"
            type="button"
            class="inline-flex items-center gap-1 rounded-full border border-border bg-accent px-2.5 py-1 text-xs text-accent-foreground transition hover:bg-accent/80"
            @click="emitClear('price')"
        >
            <span>{{ priceLabel }}</span>
            <X class="size-3" />
        </button>
    </div>
</template>
