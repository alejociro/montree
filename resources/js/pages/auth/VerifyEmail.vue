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
        title: 'Verify email',
        description:
            'Please verify your email address by clicking on the link we just emailed to you.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Email verification" />

    <Alert
        v-if="status === 'verification-link-sent'"
        class="mb-4 border-primary/40 bg-primary/10 text-primary"
    >
        <MailCheck class="size-4" />
        <AlertTitle>Revisá tu casilla</AlertTitle>
        <AlertDescription class="text-primary/90">
            Te enviamos un nuevo link de verificación al email que indicaste en
            tu registro.
        </AlertDescription>
    </Alert>

    <p class="mb-6 text-center text-sm text-muted-foreground">
        Verificá tu email para reservar tours y recibir tus confirmaciones.
    </p>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <Button :disabled="processing" variant="secondary">
            <Spinner v-if="processing" />
            Resend verification email
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            Log out
        </TextLink>
    </Form>
</template>
