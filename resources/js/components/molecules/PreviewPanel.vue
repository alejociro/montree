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
</script>

<template>
    <Card class="sticky top-6">
        <CardHeader>
            <CardTitle>Vista previa</CardTitle>
            <CardDescription>
                Así se verán los colores en la tienda pública.
            </CardDescription>
        </CardHeader>

        <CardContent>
            <div
                class="overflow-hidden rounded-lg border border-input"
                aria-label="Tenant branding preview"
            >
                <div
                    class="px-5 py-6 text-white"
                    :style="{ backgroundColor: safePrimary }"
                >
                    <p
                        class="text-xs font-medium tracking-wider uppercase opacity-80"
                    >
                        {{ tenantName }}
                    </p>
                    <h3 class="mt-1 text-lg font-semibold">
                        {{
                            tagline ||
                            'Aventuras inolvidables, en armonía con la naturaleza.'
                        }}
                    </h3>
                </div>

                <div class="space-y-3 bg-background p-5">
                    <p class="text-sm font-medium">
                        Reserva tu próxima aventura
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Botón de acción principal:
                    </p>
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-md px-4 text-sm font-medium text-white shadow-xs transition-opacity hover:opacity-90"
                        :style="{ backgroundColor: safePrimary }"
                    >
                        Reservar ahora
                    </button>
                    <button
                        type="button"
                        class="ml-2 inline-flex h-9 items-center justify-center rounded-md border border-input bg-transparent px-4 text-sm font-medium shadow-xs transition-colors hover:opacity-90"
                        :style="{
                            borderColor: safeSecondary,
                            color: safeSecondary,
                        }"
                    >
                        Saber más
                    </button>
                </div>
            </div>
        </CardContent>

        <CardFooter
            class="flex flex-col items-start gap-1 text-xs text-muted-foreground"
        >
            <span>
                Primario:
                <code class="font-mono">{{ safePrimary }}</code>
            </span>
            <span>
                Secundario:
                <code class="font-mono">{{ safeSecondary }}</code>
            </span>
        </CardFooter>
    </Card>
</template>
