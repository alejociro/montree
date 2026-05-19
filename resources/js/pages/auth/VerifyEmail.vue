<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { MailCheck } from 'lucide-vue-next';
import TextLink from '@/components/TextLink.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Verificar correo',
        description:
            'Verifica tu correo electrónico haciendo clic en el enlace que te enviamos.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Verificar correo" />

    <Alert
        v-if="status === 'verification-link-sent'"
        class="mb-4 border-primary/40 bg-primary/10 text-primary"
    >
        <MailCheck class="size-4" />
        <AlertTitle>Revisa tu bandeja de entrada</AlertTitle>
        <AlertDescription class="text-primary/90">
            Te enviamos un nuevo enlace de verificación al correo que indicaste
            en tu registro.
        </AlertDescription>
    </Alert>

    <p class="mb-6 text-center text-sm text-muted-foreground">
        Verifica tu correo para poder reservar tours y recibir tus
        confirmaciones.
    </p>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <Button :disabled="processing" variant="secondary">
            <Spinner v-if="processing" />
            Reenviar correo de verificación
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            Cerrar sesión
        </TextLink>
    </Form>
</template>
