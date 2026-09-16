<script setup lang="ts">
import { computed } from 'vue';
import {
    barPath,
    chartColor,
    niceMax
    
} from '@/lib/chart';
import type {ChartSeries} from '@/lib/chart';

type Props = {
    labels: string[];
    series: ChartSeries[];
    caption: string;
    stacked?: boolean;
    valueLabel?: string;
    formatValue?: (value: number) => string;
};

const props = withDefaults(defineProps<Props>(), {
    stacked: false,
    valueLabel: undefined,
    formatValue: (value: number) => String(value),
});

const WIDTH = 720;
const HEIGHT = 260;
const PAD_LEFT = 52;
const PAD_RIGHT = 12;
const PAD_TOP = 16;
const PAD_BOTTOM = 34;
const GAP = 2;

const plotWidth = WIDTH - PAD_LEFT - PAD_RIGHT;
const plotHeight = HEIGHT - PAD_TOP - PAD_BOTTOM;

const max = computed(() =>
    niceMax(
        Math.max(
            ...props.labels.map((_, index) =>
                props.stacked
                    ? props.series.reduce(
                          (total, serie) => total + (serie.values[index] ?? 0),
                          0,
                      )
                    : Math.max(
                          ...props.series.map(
                              (serie) => serie.values[index] ?? 0,
                          ),
                          0,
                      ),
            ),
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

const slotWidth = computed(() => plotWidth / Math.max(props.labels.length, 1));

const bandWidth = computed(() => slotWidth.value * 0.62);

const barWidth = computed(() =>
    props.stacked
        ? bandWidth.value
        : bandWidth.value / Math.max(props.series.length, 1),
);

type Bar = {
    key: string;
    path: string;
    color: string;
    title: string;
};

const bars = computed<Bar[]>(() => {
    const result: Bar[] = [];

    props.labels.forEach((label, index) => {
        const bandStart =
            PAD_LEFT +
            slotWidth.value * index +
            (slotWidth.value - bandWidth.value) / 2;
        let stackedBottom = PAD_TOP + plotHeight;

        props.series.forEach((serie, seriesIndex) => {
            const value = serie.values[index] ?? 0;
            const height = (value / max.value) * plotHeight;

            if (height <= 0) {
                return;
            }

            const x = props.stacked
                ? bandStart
                : bandStart + barWidth.value * seriesIndex;
            const y = props.stacked
                ? stackedBottom - height
                : PAD_TOP + plotHeight - height;
            const drawnHeight = props.stacked
                ? Math.max(height - GAP, 1)
                : height;

            result.push({
                key: `${serie.name}-${label}`,
                path: barPath(
                    x + (props.stacked ? 0 : GAP / 2),
                    y,
                    Math.max(barWidth.value - (props.stacked ? 0 : GAP), 1),
                    drawnHeight,
                    4,
                ),
                color: chartColor(seriesIndex),
                title: `${serie.name} · ${label}: ${props.formatValue(value)}`,
            });

            stackedBottom -= height;
        });
    });

    return result;
});

const labelPositions = computed(() =>
    props.labels.map((label, index) => ({
        label,
        x: PAD_LEFT + slotWidth.value * (index + 0.5),
    })),
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
                    v-for="position in labelPositions"
                    :key="position.label"
                    :x="position.x"
                    :y="HEIGHT - 12"
                    text-anchor="middle"
                >
                    {{ position.label }}
                </text>
            </g>

            <path
                v-for="bar in bars"
                :key="bar.key"
                :d="bar.path"
                :fill="bar.color"
            >
                <title>{{ bar.title }}</title>
            </path>
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
