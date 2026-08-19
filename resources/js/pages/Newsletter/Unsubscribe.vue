<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { useApi } from '@/composables/useApi';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps<{ token: string }>();
const done = ref(false);
const processing = ref(false);
const api = useApi();

function confirmUnsubscribe() {
    processing.value = true;
    void api.post(
        '/api/v1/newsletter/unsubscribe',
        { token: props.token },
        {
            onSuccess: () => {
                done.value = true;
                toast.success(t('Te diste de baja del newsletter'));
            },
            onError: () => toast.error(t('No pudimos procesar la baja')),
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="$t('Darse de baja')" />
    <div class="container mx-auto max-w-md space-y-6 px-4 py-16 text-center">
        <h1 class="text-2xl font-bold">
            {{ $t('Darse de baja del newsletter') }}
        </h1>
        <p v-if="!done" class="text-muted-foreground">
            {{ $t('¿Confirmas que quieres dejar de recibir nuestros emails?') }}
        </p>
        <Button v-if="!done" :disabled="processing" @click="confirmUnsubscribe">
            {{ processing ? 'Procesando...' : 'Confirmar baja' }}
        </Button>
        <p v-else class="text-primary">
            {{
                $t(
                    'Listo. Ya no vas a recibir más correos. Puedes volver a suscribirte cuando quieras.',
                )
            }}
        </p>
    </div>
</template>
