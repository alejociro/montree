<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';

const props = defineProps<{ token: string }>();
const done = ref(false);
const processing = ref(false);

function confirmUnsubscribe() {
    processing.value = true;
    router.post(
        '/api/v1/newsletter/unsubscribe',
        { token: props.token },
        {
            preserveScroll: true,
            onSuccess: () => {
                done.value = true;
                toast.success('Te diste de baja del newsletter');
            },
            onError: () => toast.error('No pudimos procesar la baja'),
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Darse de baja" />
    <div class="container mx-auto max-w-md space-y-6 px-4 py-16 text-center">
        <h1 class="text-2xl font-bold">Darse de baja del newsletter</h1>
        <p v-if="!done" class="text-muted-foreground">
            ¿Confirmás que querés dejar de recibir nuestros emails?
        </p>
        <Button v-if="!done" :disabled="processing" @click="confirmUnsubscribe">
            {{ processing ? 'Procesando...' : 'Confirmar baja' }}
        </Button>
        <p v-else class="text-primary">
            Listo. Ya no vas a recibir más correos. Podés volver a suscribirte cuando quieras.
        </p>
    </div>
</template>
