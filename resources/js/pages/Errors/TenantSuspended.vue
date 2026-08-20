<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CircleSlash, Mail } from 'lucide-vue-next';
import { useTenantBranding } from '@/composables/useTenantBranding';

type Props = {
    tenantName: string;
    contactEmail: string;
};

defineProps<Props>();

useTenantBranding();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 text-center"
    >
        <Head :title="$t('Agencia temporalmente no disponible')" />

        <div
            class="flex size-16 items-center justify-center rounded-full bg-accent text-accent-foreground"
        >
            <CircleSlash class="size-8" />
        </div>

        <div class="max-w-md space-y-3">
            <p
                class="text-xs font-semibold tracking-widest text-accent-foreground uppercase"
            >
                {{ $t('503 · Temporalmente no disponible') }}
            </p>
            <h1 class="text-2xl font-semibold tracking-tight">
                {{
                    $t(':agency no está disponible en este momento', {
                        agency: tenantName,
                    })
                }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{
                    $t(
                        'Esta agencia se encuentra suspendida temporalmente. Vuelve más tarde o contacta al equipo si creés que es un error.',
                    )
                }}
            </p>
        </div>

        <a
            v-if="contactEmail"
            :href="`mailto:${contactEmail}`"
            class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-input bg-transparent px-5 text-sm font-medium shadow-xs transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <Mail class="size-4" />
            {{ contactEmail }}
        </a>
    </div>
</template>
