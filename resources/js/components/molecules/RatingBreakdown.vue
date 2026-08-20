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

const stars = computed(() => '★'.repeat(Math.round(Number(props.average))));
</script>

<template>
    <div>
        <p class="text-[56px] leading-none font-semibold tracking-tight">
            {{ average }}
        </p>
        <p class="mt-2 tracking-[2px] text-primary" aria-hidden="true">
            {{ stars }}
        </p>
        <p
            class="mt-1.5 mb-3.5 text-[11px] tracking-[0.08em] text-muted-foreground uppercase"
        >
            {{ $tc(':count reseña|:count reseñas', count) }}
        </p>

        <div
            v-for="star in [5, 4, 3, 2, 1] as const"
            :key="star"
            class="grid grid-cols-[12px_1fr_34px] items-center gap-2.5 py-0.5 text-xs text-muted-foreground"
        >
            <span>{{ star }}</span>
            <span class="h-1.5 overflow-hidden rounded-full bg-brand-green-100">
                <span
                    class="block h-full bg-primary"
                    :style="{
                        width:
                            pct(
                                distribution[
                                    String(star) as keyof typeof distribution
                                ],
                            ) + '%',
                    }"
                />
            </span>
            <span class="text-right">
                {{
                    pct(
                        distribution[String(star) as keyof typeof distribution],
                    )
                }}%
            </span>
        </div>
    </div>
</template>
