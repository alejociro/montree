<script setup lang="ts">
import { computed } from 'vue';

type Props = {
    points: number[];
    height?: number;
    width?: number;
};

const props = withDefaults(defineProps<Props>(), {
    height: 56,
    width: 240,
});

const path = computed(() => {
    const points = props.points;

    if (points.length === 0) {
        return '';
    }

    if (points.length === 1) {
        const y = props.height / 2;

        return `M 0 ${y} L ${props.width} ${y}`;
    }

    const max = Math.max(...points);
    const min = Math.min(...points);
    const range = max - min || 1;
    const stepX = props.width / (points.length - 1);

    return points
        .map((value, index) => {
            const x = index * stepX;
            const normalized = (value - min) / range;
            const y = props.height - normalized * props.height;

            return `${index === 0 ? 'M' : 'L'} ${x.toFixed(2)} ${y.toFixed(2)}`;
        })
        .join(' ');
});

const areaPath = computed(() => {
    if (!path.value) {
        return '';
    }

    return `${path.value} L ${props.width} ${props.height} L 0 ${props.height} Z`;
});
</script>

<template>
    <svg
        :width="props.width"
        :height="props.height"
        :viewBox="`0 0 ${props.width} ${props.height}`"
        class="overflow-visible"
    >
        <path
            v-if="areaPath"
            :d="areaPath"
            fill="currentColor"
            fill-opacity="0.1"
            class="text-primary"
        />
        <path
            v-if="path"
            :d="path"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round"
            class="text-primary"
        />
        <text
            v-if="!path"
            :x="props.width / 2"
            :y="props.height / 2"
            text-anchor="middle"
            dominant-baseline="middle"
            class="fill-muted-foreground text-xs"
        >
            Sin datos
        </text>
    </svg>
</template>
