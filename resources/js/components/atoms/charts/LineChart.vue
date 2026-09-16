<script setup lang="ts">
import { computed } from 'vue';
import { chartColor, niceMax  } from '@/lib/chart';
import type {ChartSeries} from '@/lib/chart';

type Props = {
    labels: string[];
    series: ChartSeries[];
    caption: string;
    valueLabel?: string;
    average?: number | null;
    averageLabel?: string;
    formatValue?: (value: number) => string;
};

const props = withDefaults(defineProps<Props>(), {
    valueLabel: undefined,
    average: null,
    averageLabel: undefined,
    formatValue: (value: number) => String(value),
});

const WIDTH = 720;
const HEIGHT = 260;
const PAD_LEFT = 52;
const PAD_RIGHT = 12;
const PAD_TOP = 16;
const PAD_BOTTOM = 34;

const plotWidth = WIDTH - PAD_LEFT - PAD_RIGHT;
const plotHeight = HEIGHT - PAD_TOP - PAD_BOTTOM;

const max = computed(() =>
    niceMax(
        Math.max(
            ...props.series.flatMap((serie) => serie.values),
            props.average ?? 0,
            0,
        ),
    ),
);

const ticks = computed(() =>
    [0, 0.25, 0.5, 0.75, 1].map((ratio) => ({
        value: max.value * ratio,
        y: PAD_TOP + plotHeight - plotHeight * ratio,
    })),
);

function pointX(index: number): number {
    const steps = Math.max(props.labels.length - 1, 1);

    return PAD_LEFT + (plotWidth / steps) * index;
}

function pointY(value: number): number {
    return PAD_TOP + plotHeight - (value / max.value) * plotHeight;
}

const lines = computed(() =>
    props.series.map((serie, seriesIndex) => ({
        name: serie.name,
        color: chartColor(seriesIndex),
        points: serie.values
            .map((value, index) => `${pointX(index)},${pointY(value)}`)
            .join(' '),
        markers: serie.values.map((value, index) => ({
            cx: pointX(index),
            cy: pointY(value),
            title: `${serie.name} · ${props.labels[index]}: ${props.formatValue(value)}`,
        })),
    })),
);

const averageY = computed(() =>
    props.average === null ? null : pointY(props.average),
);
</script>

<template>
    <div>
        <svg
            :viewBox="`0 0 ${WIDTH} ${HEIGHT}`"
            class="h-auto w-full"
            preserveAspectRatio="xMidYMid meet"
            role="img"
            :aria-label="caption"
        >
            <g class="text-border">
                <line
                    v-for="tick in ticks"
                    :key="tick.y"
                    :x1="PAD_LEFT"
                    :x2="WIDTH - PAD_RIGHT"
                    :y1="tick.y"
                    :y2="tick.y"
                    stroke="currentColor"
                    stroke-width="1"
                />
            </g>

            <line
                v-if="averageY !== null"
                :x1="PAD_LEFT"
                :x2="WIDTH - PAD_RIGHT"
                :y1="averageY"
                :y2="averageY"
                class="text-muted-foreground"
                stroke="currentColor"
                stroke-width="1.5"
                stroke-dasharray="5 4"
            />

            <g class="fill-muted-foreground text-[11px]">
                <text
                    v-for="tick in ticks"
                    :key="`v-${tick.y}`"
                    :x="PAD_LEFT - 8"
                    :y="tick.y + 4"
                    text-anchor="end"
                >
                    {{ formatValue(tick.value) }}
                </text>
                <text
                    v-for="(label, index) in labels"
                    :key="label"
                    :x="pointX(index)"
                    :y="HEIGHT - 12"
                    text-anchor="middle"
                >
                    {{ label }}
                </text>
                <text
                    v-if="averageY !== null && averageLabel"
                    :x="WIDTH - PAD_RIGHT"
                    :y="averageY - 6"
                    text-anchor="end"
                >
                    {{ averageLabel }}
                </text>
            </g>

            <g v-for="line in lines" :key="line.name">
                <polyline
                    :points="line.points"
                    fill="none"
                    :stroke="line.color"
                    stroke-width="2"
                    stroke-linejoin="round"
                    stroke-linecap="round"
                />
                <circle
                    v-for="marker in line.markers"
                    :key="marker.title"
                    :cx="marker.cx"
                    :cy="marker.cy"
                    r="4.5"
                    :fill="line.color"
                    stroke="var(--background)"
                    stroke-width="2"
                >
                    <title>{{ marker.title }}</title>
                </circle>
            </g>
        </svg>

        <table class="sr-only">
            <caption>
                {{
                    caption
                }}
            </caption>
            <thead>
                <tr>
                    <th scope="col">{{ valueLabel ?? caption }}</th>
                    <th v-for="label in labels" :key="label" scope="col">
                        {{ label }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="serie in series" :key="serie.name">
                    <th scope="row">{{ serie.name }}</th>
                    <td v-for="(value, index) in serie.values" :key="index">
                        {{ formatValue(value) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
