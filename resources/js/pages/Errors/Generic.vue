<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ShieldAlert } from 'lucide-vue-next';
import { computed } from 'vue';

type Props = {
    status: number;
    /** A donde vuelve el usuario: su home de rol, resuelto en el servidor. */
    homeUrl: string;
};

const props = defineProps<Props>();

/**
 * WHY: hasta ahora un 403 o un 404 salian como la pagina cruda de Symfony
 * ("403 Forbidden", en ingles y sin marca), sin ninguna salida. Es la pantalla
 * que ve cualquiera que abra un enlace que ya no le corresponde, asi que tiene
 * que decir que paso y como volver.
 */
const copy = computed(() => {
    switch (props.status) {
        case 403:
            return {
                title: 'No tenés acceso a esta pantalla',
                description:
                    'Tu rol no incluye este módulo. Si creés que deberías verlo, pedile acceso a quien administra tu agencia.',
            };
        case 404:
            return {
                title: 'Esta página no existe',
                description:
                    'El enlace puede estar mal escrito o el contenido pudo haberse eliminado.',
            };
        case 419:
            return {
                title: 'Tu sesión expiró',
                description:
                    'Por seguridad cerramos las sesiones inactivas. Volvé a iniciar sesión para continuar.',
            };
        case 429:
            return {
                title: 'Demasiados intentos',
                description: 'Esperá un momento antes de volver a intentarlo.',
            };
        case 503:
            return {
                title: 'Estamos en mantenimiento',
                description: 'Volvé a intentarlo en unos minutos.',
            };
        default:
            return {
                title: 'Algo salió mal',
                description:
                    'Tuvimos un problema procesando tu solicitud. Si vuelve a pasar, avisale al equipo.',
            };
    }
});
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 text-center"
    >
        <Head :title="copy.title" />

        <div
            class="flex size-16 items-center justify-center rounded-full bg-primary/10 text-primary"
        >
            <ShieldAlert class="size-8" />
        </div>

        <div class="max-w-md space-y-3">
            <p
                class="text-xs font-semibold tracking-widest text-primary uppercase"
            >
                Error {{ status }}
            </p>
            <h1 class="text-2xl font-semibold tracking-tight">
                {{ copy.title }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ copy.description }}
            </p>
        </div>

        <Link
            :href="homeUrl"
            class="inline-flex h-10 items-center justify-center rounded-md bg-primary px-5 text-sm font-medium text-primary-foreground shadow-xs transition-opacity hover:opacity-90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            Volver al inicio
        </Link>
    </div>
</template>
