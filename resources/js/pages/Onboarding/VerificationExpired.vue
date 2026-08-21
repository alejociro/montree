<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckCircle2, Leaf, MailWarning } from 'lucide-vue-next';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { home, login } from '@/routes';
import { resendVerification } from '@/routes/onboarding';

const { t } = useTranslations();

const email = ref('');
const emailError = ref<string | null>(null);
const resending = ref(false);
const resent = ref(false);

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function resend() {
    if (resending.value) {
        return;
    }

    emailError.value = null;

    if (!EMAIL_PATTERN.test(email.value)) {
        emailError.value = t('Ingresa un correo electrónico válido.');

        return;
    }

    resending.value = true;

    router.post(
        resendVerification.url(),
        { email: email.value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                resent.value = true;
            },
            onError: (errors) => {
                emailError.value =
                    errors.email ?? t('No pudimos reenviar el email.');
            },
            onFinish: () => {
                resending.value = false;
            },
        },
    );
}
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center bg-background px-6 py-12 text-foreground"
    >
        <Head :title="$t('Enlace expirado — Montree')" />

        <Link :href="home().url" class="mb-10 inline-flex items-center gap-2">
            <span
                class="flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground"
            >
                <Leaf class="size-4" />
            </span>
            <span class="text-base font-semibold tracking-tight">{{
                $t('Montree')
            }}</span>
        </Link>

        <div class="w-full max-w-md text-center">
            <div
                class="mx-auto mb-6 flex size-16 items-center justify-center rounded-full bg-accent text-accent-foreground"
            >
                <MailWarning class="size-8" />
            </div>

            <p
                class="text-xs font-semibold tracking-widest text-accent-foreground uppercase"
            >
                {{ $t('Enlace no válido') }}
            </p>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight">
                {{ $t('Este enlace de verificación expiró') }}
            </h1>
            <p class="mt-3 text-sm text-muted-foreground">
                {{
                    $t(
                        'Los enlaces de confirmación caducan por seguridad. Ingresa tu correo y te enviamos uno nuevo.',
                    )
                }}
            </p>

            <div
                v-if="resent"
                role="status"
                class="mt-6 flex items-center justify-center gap-2 rounded-md border border-secondary bg-secondary px-3 py-2.5 text-sm text-primary"
            >
                <CheckCircle2 class="size-4 shrink-0" />
                <span>{{
                    $t('Si la cuenta existe, te reenviamos el email.')
                }}</span>
            </div>

            <form
                v-else
                class="mt-8 space-y-3 text-left"
                novalidate
                @submit.prevent="resend"
            >
                <div class="grid gap-2">
                    <Label for="email">{{ $t('Correo electrónico') }}</Label>
                    <Input
                        id="email"
                        v-model="email"
                        type="email"
                        autocomplete="email"
                        :placeholder="$t('tu@correo.com')"
                        :aria-invalid="Boolean(emailError)"
                    />
                    <InputError :message="emailError ?? undefined" />
                </div>

                <Button
                    type="submit"
                    class="w-full"
                    :disabled="resending"
                    data-test="resend-verification-button"
                >
                    <Spinner v-if="resending" />
                    {{ $t('Reenviar email de verificación') }}
                </Button>
            </form>

            <p class="mt-8 text-center text-sm text-muted-foreground">
                {{ $t('¿Ya confirmaste?') }}
                <Link
                    :href="login().url"
                    class="font-medium text-primary underline-offset-4 hover:underline"
                >
                    {{ $t('Inicia sesión') }}
                </Link>
            </p>
        </div>
    </div>
</template>
