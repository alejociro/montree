<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Clock, Leaf } from 'lucide-vue-next';

type Props = {
    tenantName: string;
};

defineProps<Props>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 text-center text-foreground"
    >
        <Head :title="$t('Agencia pendiente de activación')" />

        <span class="inline-flex items-center gap-2 text-muted-foreground">
            <span
                class="flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground"
            >
                <Leaf class="size-4" />
            </span>
            <span
                class="text-base font-semibold tracking-tight text-foreground"
                >{{ $t('Montree') }}</span
            >
        </span>

        <div
            class="flex size-16 items-center justify-center rounded-full bg-accent text-accent-foreground"
        >
            <Clock class="size-8" />
        </div>

        <div class="max-w-md space-y-3">
            <p
                class="text-xs font-semibold tracking-widest text-accent-foreground uppercase"
            >
                {{ $t('Pendiente de activación') }}
            </p>
            <h1 class="text-2xl font-semibold tracking-tight">
                {{
                    $t(':agency todavía no está activa', { agency: tenantName })
                }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{
                    $t(
                        'Esta agencia se creó pero aún no confirmó su correo. En cuanto el equipo verifique su email, el sitio quedará disponible.',
                    )
                }}
            </p>
        </div>
    </div>
</template>
