<script setup lang="ts">
import { computed } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { readableInk } from '@/lib/color';

type Props = {
    tenantName: string;
    tagline?: string | null;
    primaryColor: string;
    secondaryColor: string;
};

const props = defineProps<Props>();

const HEX_REGEX = /^#[0-9A-Fa-f]{6}$/;

const safePrimary = computed(() =>
    HEX_REGEX.test(props.primaryColor) ? props.primaryColor : '#16a34a',
);

const safeSecondary = computed(() =>
    HEX_REGEX.test(props.secondaryColor) ? props.secondaryColor : '#0f766e',
);

/**
 * WHY: la vista previa escribía en blanco fijo sobre el color elegido. Con un
 * primario claro —un amarillo, un beige— el texto desaparecía justo en la
 * pantalla donde la agencia decide el color. La tinta se calcula igual que en
 * la aplicación real (`--primary-foreground`), así que lo que se ve aquí es lo
 * que se verá en la tienda.
 */
const onPrimary = computed(() => readableInk(safePrimary.value));
const onSecondary = computed(() => readableInk(safeSecondary.value));
</script>

<template>
    <Card class="sticky top-6">
        <CardHeader>
            <CardTitle>{{ $t('Vista previa') }}</CardTitle>
            <CardDescription>
                {{ $t('Así se verán los colores en la tienda pública.') }}
            </CardDescription>
        </CardHeader>

        <CardContent>
            <div
                class="overflow-hidden rounded-lg border border-input"
                :aria-label="$t('Tenant branding preview')"
            >
                <div
                    class="px-5 py-6"
                    :style="{
                        backgroundColor: safePrimary,
                        color: onPrimary,
                    }"
                >
                    <p
                        class="text-xs font-medium tracking-wider uppercase opacity-80"
                    >
                        {{ tenantName }}
                    </p>
                    <h3 class="mt-1 text-lg font-semibold">
                        {{
                            tagline ||
                            $t(
                                'Aventuras inolvidables, en armonía con la naturaleza.',
                            )
                        }}
                    </h3>
                </div>

                <div class="space-y-3 bg-background p-5">
                    <p class="text-sm font-medium">
                        {{ $t('Reserva tu próxima aventura') }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ $t('Botón de acción principal:') }}
                    </p>
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-md px-4 text-sm font-medium shadow-xs transition-opacity hover:opacity-90"
                        :style="{
                            backgroundColor: safePrimary,
                            color: onPrimary,
                        }"
                    >
                        {{ $t('Reservar ahora') }}
                    </button>
                    <!--
                      El secundario se muestra RELLENO, no como borde: es el
                      color que pinta chips, etiquetas y botones secundarios en
                      la tienda, y de contorno no se alcanzaba a juzgar.
                    -->
                    <button
                        type="button"
                        class="ml-2 inline-flex h-9 items-center justify-center rounded-md px-4 text-sm font-medium shadow-xs transition-opacity hover:opacity-90"
                        :style="{
                            backgroundColor: safeSecondary,
                            color: onSecondary,
                        }"
                    >
                        {{ $t('Saber más') }}
                    </button>
                </div>
            </div>
        </CardContent>

        <CardFooter
            class="flex flex-col items-start gap-1 text-xs text-muted-foreground"
        >
            <span>
                {{ $t('Primario:') }}
                <code class="font-mono">{{ safePrimary }}</code>
            </span>
            <span>
                {{ $t('Secundario:') }}
                <code class="font-mono">{{ safeSecondary }}</code>
            </span>
        </CardFooter>
    </Card>
</template>
