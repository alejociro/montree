/**
 * Contraste adaptativo para los colores del tenant.
 *
 * WHY: la agencia elige su primario y su secundario libremente. Un rojo oscuro
 * y un amarillo pálido son colores válidos, pero el texto encima no puede ser
 * el mismo en los dos casos: sobre el rojo hay que escribir en crema y sobre el
 * amarillo en tinta. Antes se decidía mirando solo la `L` del HSL, y una `L`
 * media con mucha saturación (un amarillo `50% 60%`) caía del lado equivocado
 * porque la luminosidad percibida no es la `L` de HSL.
 *
 * Aquí se calcula la luminancia relativa de WCAG 2.1 y se elige la tinta que
 * gana el contraste. El mismo cálculo sirve para bajar (o subir) un color de
 * marca hasta que se pueda LEER sobre el fondo de la página, que es el otro
 * caso donde la letra se perdía: un `text-primary` con el primario claro.
 */

/** Tinta clara del sistema (`--brand-cream`). */
export const INK_LIGHT = '#f6f1e4';

/** Tinta oscura del sistema (`--brand-ink`). */
export const INK_DARK = '#14301f';

export type Rgb = [number, number, number];

let probe: HTMLElement | null = null;

/**
 * Resuelve CUALQUIER color CSS —`#fff`, `hsl(...)`, `rgb(...)`, `oklch(...)`—
 * a RGB usando el propio motor del navegador. Es la única forma honesta de
 * leer un color que llega ya renderizado en una variable CSS sin reimplementar
 * medio módulo de color de CSS.
 */
export function toRgb(css: string): Rgb | null {
    const value = css.trim();

    if (value === '' || typeof document === 'undefined') {
        return null;
    }

    const hex = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(value);

    if (hex) {
        const digits =
            hex[1].length === 3
                ? hex[1]
                      .split('')
                      .map((character) => character + character)
                      .join('')
                : hex[1];

        return [
            Number.parseInt(digits.slice(0, 2), 16),
            Number.parseInt(digits.slice(2, 4), 16),
            Number.parseInt(digits.slice(4, 6), 16),
        ];
    }

    if (probe === null) {
        probe = document.createElement('span');
        probe.setAttribute('aria-hidden', 'true');
        probe.style.cssText =
            'position:absolute;width:0;height:0;opacity:0;pointer-events:none';
        document.body.appendChild(probe);
    }

    probe.style.color = '';
    probe.style.color = value;

    if (probe.style.color === '') {
        return null;
    }

    const parts = /rgba?\(([^)]+)\)/.exec(getComputedStyle(probe).color);

    if (!parts) {
        return null;
    }

    const channels = parts[1]
        .split(/[\s,/]+/)
        .filter((part) => part !== '')
        .slice(0, 3)
        .map((part) => Number.parseFloat(part));

    return channels.length === 3 && channels.every(Number.isFinite)
        ? (channels as Rgb)
        : null;
}

/** Luminancia relativa de WCAG 2.1 (0 = negro, 1 = blanco). */
export function relativeLuminance([r, g, b]: Rgb): number {
    const channel = (raw: number): number => {
        const value = raw / 255;

        return value <= 0.03928
            ? value / 12.92
            : ((value + 0.055) / 1.055) ** 2.4;
    };

    return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
}

/** Razón de contraste de WCAG 2.1 entre dos colores (1 a 21). */
export function contrastRatio(a: Rgb, b: Rgb): number {
    const first = relativeLuminance(a);
    const second = relativeLuminance(b);
    const lighter = Math.max(first, second);
    const darker = Math.min(first, second);

    return (lighter + 0.05) / (darker + 0.05);
}

/**
 * Luminancia a partir de la cual se escribe en tinta oscura.
 *
 * WHY no el máximo contraste a secas: sobre un rojo puro la tinta oscura gana
 * por 3.63 contra 3.55, un empate técnico, y el resultado en pantalla era verde
 * tinta sobre rojo — ilegible en un número de 11 px. El umbral de luminancia
 * manda a los colores saturados (rojo, azul, verde intenso) a tinta clara, que
 * es como se leen de verdad, y reserva la oscura para los claros: amarillos,
 * cianes, beiges.
 */
const DARK_INK_LUMINANCE = 0.42;

/** Mínimo aceptable para texto grande y elementos de interfaz (WCAG 2.1). */
const MIN_UI_CONTRAST = 3;

/**
 * Tinta legible ENCIMA de `background`: la crema o el verde tinta de la paleta.
 */
export function readableInk(background: string | Rgb): string {
    const rgb = typeof background === 'string' ? toRgb(background) : background;

    if (rgb === null) {
        return INK_LIGHT;
    }

    const light = toRgb(INK_LIGHT) ?? [255, 255, 255];
    const dark = toRgb(INK_DARK) ?? [0, 0, 0];
    const preferDark = relativeLuminance(rgb) >= DARK_INK_LUMINANCE;

    const preferred = preferDark ? dark : light;
    const other = preferDark ? light : dark;

    // La preferencia cede solo si de verdad no se lee y la otra sí.
    if (
        contrastRatio(rgb, preferred) < MIN_UI_CONTRAST &&
        contrastRatio(rgb, other) >= MIN_UI_CONTRAST
    ) {
        return preferDark ? INK_LIGHT : INK_DARK;
    }

    return preferDark ? INK_DARK : INK_LIGHT;
}

function parseHslTriplet(triplet: string): [number, number, number] | null {
    const parts = triplet
        .trim()
        .replace(/%/g, '')
        .split(/[\s,]+/)
        .map((part) => Number.parseFloat(part));

    return parts.length >= 3 && parts.slice(0, 3).every(Number.isFinite)
        ? [parts[0], parts[1], parts[2]]
        : null;
}

/**
 * Tinta legible sobre un triplete HSL crudo (`"142 76% 36%"`), que es como el
 * backend manda los colores del tenant.
 */
export function readableInkForTriplet(triplet: string): string {
    return readableInk(`hsl(${triplet})`);
}

/**
 * Versión del color de marca que SÍ se lee como texto sobre `background`.
 *
 * Mantiene el tono y la saturación —sigue siendo el color de la agencia— y
 * mueve solo la luminosidad, en pasos de 2 %, hacia el lado contrario al del
 * fondo, hasta alcanzar el 4.5:1 de WCAG AA. Si ni en el extremo llega (un
 * amarillo puro sobre crema nunca lo hace), devuelve el extremo: es lo más
 * legible posible sin dejar de ser el color de la marca.
 */
export function textSafeHsl(triplet: string, background: string): string {
    const hsl = parseHslTriplet(triplet);
    const backgroundRgb = toRgb(background);

    if (hsl === null || backgroundRgb === null) {
        return `hsl(${triplet})`;
    }

    const [hue, saturation] = hsl;
    const darken = relativeLuminance(backgroundRgb) > 0.4;
    let lightness = hsl[2];

    for (let step = 0; step < 50; step += 1) {
        const candidate = toRgb(`hsl(${hue} ${saturation}% ${lightness}%)`);

        if (candidate === null) {
            break;
        }

        if (contrastRatio(candidate, backgroundRgb) >= 4.5) {
            break;
        }

        const next = darken ? lightness - 2 : lightness + 2;

        if (next < 0 || next > 100) {
            break;
        }

        lightness = next;
    }

    return `hsl(${hue} ${saturation}% ${lightness}%)`;
}
