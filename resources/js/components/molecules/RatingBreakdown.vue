<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    distribution: Record<'1' | '2' | '3' | '4' | '5', number>;
    average: string;
    count: number;
}>();

const total = computed(() =>
    Object.values(props.distribution).reduce((sum, n) => sum + n, 0),
);
const pct = (n: number) =>
    total.value === 0 ? 0 : Math.round((n / total.value) * 100);
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-baseline gap-2">
            <span class="text-3xl font-bold">{{ average }}</span>
            <span class="text-sm text-muted-foreground"
                >({{ count }} reseñas)</span
            >
        </div>
        <div class="space-y-1">
            <div
                v-for="star in [5, 4, 3, 2, 1] as const"
                :key="star"
                class="flex items-center gap-2 text-sm"
            >
                <span class="w-6 text-muted-foreground">{{ star }}★</span>
                <div class="h-2 flex-1 overflow-hidden rounded bg-muted">
                    <div
                        class="h-full bg-primary transition-all"
                        :style="{
                            width:
                                pct(
                                    distribution[
                                        String(
                                            star,
                                        ) as keyof typeof distribution
                                    ],
                                ) + '%',
                        }"
                    />
                </div>
                <span class="w-8 text-right text-muted-foreground">
                    {{
                        distribution[String(star) as keyof typeof distribution]
                    }}
                </span>
            </div>
        </div>
    </div>
</template>
