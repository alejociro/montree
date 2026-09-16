<script setup lang="ts">
type LegendItem = {
    name: string;
    color: string;
};

defineProps<{
    items: LegendItem[];
}>();
</script>

<template>
    <ul class="flex flex-wrap items-center gap-x-4 gap-y-1.5">
        <li
            v-for="item in items"
            :key="item.name"
            class="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
        >
            <span
                class="size-2.5 shrink-0 rounded-[3px]"
                :style="{ backgroundColor: item.color }"
                aria-hidden="true"
            />
            {{ item.name }}
        </li>
    </ul>
</template>
