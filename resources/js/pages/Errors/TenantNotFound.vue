<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Compass } from 'lucide-vue-next';
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 text-center"
    >
        <Head title="Agencia no encontrada" />

        <div
            class="flex size-16 items-center justify-center rounded-full bg-primary/10 text-primary"
        >
            <Compass class="size-8" />
        </div>

        <div class="max-w-md space-y-3">
            <p
                class="text-xs font-semibold tracking-widest text-primary uppercase"
            >
                404 · Agencia no encontrada
            </p>
            <h1 class="text-2xl font-semibold tracking-tight">
                Esta dirección no corresponde a ninguna agencia
            </h1>
            <p class="text-sm text-muted-foreground">
                Es posible que el subdominio esté mal escrito o que la agencia
                ya no esté activa en MONTREE.
            </p>
        </div>

        <a
            href="https://montree.app"
            class="inline-flex h-10 items-center justify-center rounded-md bg-primary px-5 text-sm font-medium text-primary-foreground shadow-xs transition-opacity hover:opacity-90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            Ir a montree.app
        </a>
    </div>
</template>
