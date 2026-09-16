/**
 * Las ocho ranuras categóricas de `app.css`. Se asignan SIEMPRE en este orden y
 * nunca se ciclan: una serie novena entra agrupada en «Otros» antes de llegar
 * acá, porque repetir un color haría que dos agencias distintas se lean iguales.
 */
export const CHART_COLORS = [
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-4)',
    'var(--chart-5)',
    'var(--chart-6)',
    'var(--chart-7)',
    'var(--chart-8)',
] as const;

export const MAX_CHART_SERIES = CHART_COLORS.length;

export type ChartSeries = {
    name: string;
    values: number[];
};

export function chartColor(index: number): string {
    return CHART_COLORS[index % CHART_COLORS.length];
}

/**
 * El techo del eje: el valor más alto redondeado hacia arriba a 1, 2 o 5 por
 * década, para que las líneas de referencia caigan en números legibles.
 */
export function niceMax(max: number): number {
    if (max <= 0) {
        return 1;
    }

    const magnitude = 10 ** Math.floor(Math.log10(max));
    const normalized = max / magnitude;
    const step = normalized <= 1 ? 1 : normalized <= 2 ? 2 : normalized <= 5 ? 5 : 10;

    return step * magnitude;
}

/** Rectángulo con las dos esquinas superiores redondeadas y la base recta. */
export function barPath(
    x: number,
    y: number,
    width: number,
    height: number,
    radius: number,
): string {
    const r = Math.min(radius, width / 2, height);

    return [
        `M ${x} ${y + height}`,
        `L ${x} ${y + r}`,
        `Q ${x} ${y} ${x + r} ${y}`,
        `L ${x + width - r} ${y}`,
        `Q ${x + width} ${y} ${x + width} ${y + r}`,
        `L ${x + width} ${y + height}`,
        'Z',
    ].join(' ');
}
