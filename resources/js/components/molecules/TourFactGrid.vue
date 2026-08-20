<script setup lang="ts">
import type { TourFact } from '@/types/tour-detail';

defineProps<{
    facts: TourFact[];
}>();
</script>

<template>
    <dl class="mt-5 grid grid-cols-2 gap-2.5 lg:grid-cols-4">
        <div
            v-for="fact in facts"
            :key="fact.label"
            class="rounded-xl border border-border bg-card px-3.5 py-3"
        >
            <dt
                class="text-[11px] tracking-[0.08em] text-muted-foreground uppercase"
            >
                {{ fact.label }}
            </dt>
            <dd class="mt-0.5 text-[15px] font-bold">{{ fact.value }}</dd>
        </div>
    </dl>
</template>
