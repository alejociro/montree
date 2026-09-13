<script setup lang="ts">
import { MoreVertical } from 'lucide-vue-next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

/**
 * Menú de acciones de tres puntos.
 *
 * WHY: el sistema de diseño pide SIEMPRE un solo botón ⋯ con menú desplegable
 * en vez de una fila de botones o de iconos sueltos —en cabeceras de detalle,
 * en filas de tabla y en fichas—. Este componente concentra el disparador, el
 * tamaño del panel y la accesibilidad para que todas las superficies se
 * comporten igual; cada consumidor solo pone sus `DropdownMenuItem`.
 */
type Props = {
    /** Etiqueta accesible del disparador. */
    label?: string;
    /** Alineación del panel respecto al disparador. */
    align?: 'start' | 'center' | 'end';
    /** Ancho del panel. */
    width?: string;
    /** Variante visual del disparador. */
    variant?: 'ghost' | 'outline' | 'solid';
    class?: string;
};

const props = withDefaults(defineProps<Props>(), {
    label: undefined,
    align: 'end',
    width: 'w-56',
    variant: 'outline',
    class: undefined,
});

const variants: Record<NonNullable<Props['variant']>, string> = {
    ghost: 'border-transparent bg-transparent hover:bg-primary-soft',
    outline: 'border-border bg-card hover:bg-primary-soft',
    solid: 'border-transparent bg-card/90 hover:bg-card',
};
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger
            :aria-label="props.label ?? $t('Acciones')"
            :class="
                cn(
                    'inline-flex size-9 shrink-0 items-center justify-center rounded-full border transition focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none',
                    variants[props.variant],
                    props.class,
                )
            "
        >
            <MoreVertical class="size-4" />
        </DropdownMenuTrigger>
        <DropdownMenuContent :align="props.align" :class="props.width">
            <slot />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
